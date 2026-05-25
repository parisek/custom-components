# Twig Typography Integration Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the duplicated `mundschenk-at/php-typography` wrapper code inside `custom_components` with a dependency on `parisek/twig-typography ^1.2`, so the same logic lives in exactly one place.

**Architecture:** The upstream `parisek/twig-typography` package (1.2.0) already provides a framework-agnostic Twig extension that wraps `mundschenk-at/php-typography` and registers a `typography` filter. This plan adds a thin Drupal-side wrapper `Drupal\custom_components\Twig\TypographyExtension` that resolves the active-theme's `static/typography.yml`, parses it once per theme, and delegates the actual filtering to a cached upstream extension instance. Two existing Drupal sites consume the typography work: `TwigExtension::getTypography()` (Twig filter callsite) and `FilterTypography` (Drupal text-format filter). Both are refactored to delegate to the new wrapper. The transitive `mundschenk-at/php-typography` requirement comes via `parisek/twig-typography`, so the direct require is dropped from `composer.json`.

**Tech Stack:** Drupal 10/11, PHP 8.1+, `parisek/twig-typography: ^1.2`, PHPUnit 10, PHPStan (mglaman/phpstan-drupal), Drupal service container, Symfony YAML.

---

## File Structure

**New files:**
- `src/Twig/TypographyExtension.php` — Drupal-side wrapper Twig extension. Resolves active theme path → loads YAML → caches per-theme → delegates `applyTypography()` to upstream `Parisek\Twig\TypographyExtension`. Adds Drupal-specific render-array pass-through.
- `tests/src/Unit/Twig/TypographyExtensionTest.php` — Unit tests for the wrapper (caching, render-array pass-through, delegation, missing-YAML fallback).

**Modified files:**
- `composer.json` — drop `mundschenk-at/php-typography`, add `parisek/twig-typography: ^1.2`.
- `custom_components.services.yml` — register `custom_components.typography_twig_extension`, inject it into `custom_components.twig_extension` arguments (no — actually we remove typography from TwigExtension entirely so no DI change there).
- `src/TwigExtension.php` — remove `getTypography()`, `getDefaults()`, `getFilePath()` methods; remove `typography` TwigFilter registration; remove now-unused `use` imports (`PHP_Typography\*`, `Symfony\Component\Yaml\Yaml`).
- `src/Plugin/Filter/FilterTypography.php` — refactor to `ContainerFactoryPluginInterface`, inject the new wrapper, delegate `applyTypography()`, keep the `<blockquote>` class manipulation.
- `tests/src/Unit/TwigExtensionTest.php` — no test changes needed (it doesn't currently cover typography), but verify the suite still passes.

**Structural decisions:**
- The new wrapper lives in `src/Twig/` subdirectory (PSR-4: `Drupal\custom_components\Twig\`). `TwigExtension.php` stays at `src/` root for backward compatibility (other code may reference the namespace).
- Per-theme cache is an instance property (one extension instance per request, multiple themes possible in admin context).
- We delegate `applyTypography()`, not `getFilters()` — the wrapper exposes its own filter registration so it appears as a single Drupal-tagged service.

---

## Task 1: Update composer.json + lock

**Files:**
- Modify: `composer.json`

- [ ] **Step 1: Edit `composer.json` to swap the typography dependency**

In `require`, remove this line:
```json
"mundschenk-at/php-typography": "^6.0.0",
```

Add this line in alphabetical order (between `drupal/twig_tweak` and any next entry, or wherever fits the existing sort):
```json
"parisek/twig-typography": "^1.2",
```

Final `require` block should look like (sorted alphabetically):
```json
"require": {
    "php": ">=8.1",
    "drupal/components": "^3.2",
    "drupal/config_pages": "^2.19",
    "drupal/core": "^10 || ^11",
    "drupal/extra_field": "^3.0",
    "drupal/twig_real_content": "^1.0",
    "drupal/twig_tweak": "^3.4",
    "parisek/twig-typography": "^1.2"
}
```

- [ ] **Step 2: Run `composer validate --strict`**

Run: `composer validate --strict`
Expected: `./composer.json is valid`

- [ ] **Step 3: Verify upstream 1.2.0 is published on Packagist before continuing**

Run: `composer show parisek/twig-typography --available 2>&1 | grep -E "^versions" | head -5`
Expected: A line listing available versions including `1.2.0` (or higher).

If `1.2.0` is not yet listed, STOP — the upstream PR hasn't been merged and tagged. Wait for that before continuing.

- [ ] **Step 4: Commit the composer.json change (no install yet)**

```bash
git add composer.json
git commit -m "deps: swap mundschenk-at/php-typography for parisek/twig-typography ^1.2

The typography wrapper logic in this module duplicated the upstream
parisek/twig-typography extension. The upstream package already wraps
mundschenk-at/php-typography (which becomes transitive) and exposes
the same |typography Twig filter, so requiring it directly enables us
to delete the duplicated code in subsequent commits."
```

---

## Task 2: Write failing tests for the new Drupal wrapper

**Files:**
- Create: `tests/src/Unit/Twig/TypographyExtensionTest.php`

- [ ] **Step 1: Create the test file**

```php
<?php

namespace Drupal\Tests\custom_components\Unit\Twig;

use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\custom_components\Twig\TypographyExtension;
use PHPUnit\Framework\TestCase;
use Twig\TwigFilter;

/**
 * Tests for the Drupal-side TypographyExtension wrapper.
 *
 * @coversDefaultClass \Drupal\custom_components\Twig\TypographyExtension
 * @group custom_components
 */
class TypographyExtensionTest extends TestCase {

  /**
   * The system under test.
   */
  protected TypographyExtension $extension;

  /**
   * Mock theme manager.
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * Mock extension path resolver.
   */
  protected ExtensionPathResolver $extensionPathResolver;

  /**
   * Temp directory for fake theme paths.
   */
  protected string $tmpDir;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->tmpDir = sys_get_temp_dir() . '/custom_components_test_' . uniqid();
    mkdir($this->tmpDir . '/static', 0777, TRUE);

    $this->themeManager = $this->createMock(ThemeManagerInterface::class);
    $this->extensionPathResolver = $this->createMock(ExtensionPathResolver::class);

    $this->extension = new TypographyExtension(
      $this->themeManager,
      $this->extensionPathResolver,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    if (is_dir($this->tmpDir)) {
      array_map('unlink', glob($this->tmpDir . '/static/*') ?: []);
      @rmdir($this->tmpDir . '/static');
      @rmdir($this->tmpDir);
    }
    parent::tearDown();
  }

  /**
   * Configure the theme manager + path resolver to return $this->tmpDir.
   */
  protected function pointAtFakeTheme(string $themeName = 'fake_theme'): void {
    $activeTheme = $this->createMock(ActiveTheme::class);
    $activeTheme->method('getName')->willReturn($themeName);
    $this->themeManager->method('getActiveTheme')->willReturn($activeTheme);
    $this->extensionPathResolver
      ->method('getPath')
      ->with('theme', $themeName)
      ->willReturn($this->tmpDir);
  }

  /**
   * @covers ::getFilters
   */
  public function testRegistersTypographyFilter(): void {
    $filters = $this->extension->getFilters();
    $this->assertCount(1, $filters);
    $this->assertInstanceOf(TwigFilter::class, $filters[0]);
    $this->assertSame('typography', $filters[0]->getName());
  }

  /**
   * @covers ::applyTypography
   *
   * Render arrays must pass through untouched — the upstream extension
   * doesn't know about Drupal render arrays, so this is the wrapper's job.
   */
  public function testRenderArrayPassesThroughUntouched(): void {
    $this->pointAtFakeTheme();
    $renderArray = ['#markup' => 'hello'];
    $result = $this->extension->applyTypography($renderArray);
    $this->assertSame($renderArray, $result);
  }

  /**
   * @covers ::applyTypography
   *
   * With no YAML file present, the upstream defaults still apply
   * (PHP_Typography Settings(true)), so smart quotes happen.
   */
  public function testMissingYamlStillProcessesWithDefaults(): void {
    $this->pointAtFakeTheme();
    // No file written to $this->tmpDir/static/typography.yml.

    $result = $this->extension->applyTypography('"hello"');
    // Upstream PHP_Typography with defaults converts ASCII quotes to smart quotes.
    $this->assertStringContainsString("\xe2\x80\x9c", $result, 'left double smart quote present');
    $this->assertStringContainsString("\xe2\x80\x9d", $result, 'right double smart quote present');
  }

  /**
   * @covers ::applyTypography
   *
   * YAML config from the theme must reach the upstream PHP_Typography
   * Settings object. We verify by disabling smart_quotes and asserting
   * ASCII quotes survive unchanged.
   */
  public function testYamlConfigReachesUpstream(): void {
    $this->pointAtFakeTheme();
    file_put_contents(
      $this->tmpDir . '/static/typography.yml',
      "set_smart_quotes: false\n",
    );

    $result = $this->extension->applyTypography('"hello"');
    $this->assertStringContainsString('"hello"', $result, 'ASCII quotes survived because smart_quotes disabled in YAML');
  }

  /**
   * @covers ::applyTypography
   *
   * Per-theme cache: the YAML must only be parsed once per theme even
   * across multiple filter calls. We verify by mocking that
   * extension.path.resolver is called at most once per theme.
   */
  public function testYamlIsCachedAcrossCalls(): void {
    $activeTheme = $this->createMock(ActiveTheme::class);
    $activeTheme->method('getName')->willReturn('cached_theme');
    $this->themeManager->method('getActiveTheme')->willReturn($activeTheme);
    // expectsExactly(1) — even though applyTypography is called twice,
    // path resolution should happen only once.
    $this->extensionPathResolver
      ->expects($this->once())
      ->method('getPath')
      ->with('theme', 'cached_theme')
      ->willReturn($this->tmpDir);

    $this->extension->applyTypography('hello');
    $this->extension->applyTypography('world');
  }
}
```

- [ ] **Step 2: Run the test to verify it fails (class missing)**

Run: `vendor/bin/phpunit tests/src/Unit/Twig/TypographyExtensionTest.php`
Expected: PHPUnit error — `Class "Drupal\custom_components\Twig\TypographyExtension" not found`

---

## Task 3: Implement the Drupal wrapper to make tests pass

**Files:**
- Create: `src/Twig/TypographyExtension.php`

- [ ] **Step 1: Write the implementation**

```php
<?php

declare(strict_types=1);

namespace Drupal\custom_components\Twig;

use Drupal\Core\Extension\ExtensionPathResolver;
use Drupal\Core\Theme\ThemeManagerInterface;
use Parisek\Twig\TypographyExtension as UpstreamTypographyExtension;
use Symfony\Component\Yaml\Yaml;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Drupal-side wrapper for parisek/twig-typography.
 *
 * Resolves the active theme's `static/typography.yml`, parses it once per
 * theme, and delegates filtering to a cached upstream extension instance.
 * Also pass-through Drupal render arrays without processing.
 */
final class TypographyExtension extends AbstractExtension {

  /**
   * Per-theme cache of upstream extensions. Keyed by theme machine name.
   *
   * @var array<string, \Parisek\Twig\TypographyExtension>
   */
  private array $cache = [];

  public function __construct(
    private readonly ThemeManagerInterface $themeManager,
    private readonly ExtensionPathResolver $extensionPathResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    return [
      new TwigFilter(
        'typography',
        [$this, 'applyTypography'],
        ['is_safe' => ['html']],
      ),
    ];
  }

  /**
   * Apply the typography filter, with render-array pass-through.
   *
   * @param mixed $string
   *   The string to filter, or a render array (which is returned unchanged).
   * @param array<string, mixed> $arguments
   *   Optional per-call overrides for PHP_Typography settings.
   * @param bool $useDefaults
   *   Whether to load the upstream Settings(true) defaults.
   *
   * @return mixed
   *   Filtered string, or the original render array.
   */
  public function applyTypography(mixed $string, array $arguments = [], bool $useDefaults = TRUE): mixed {
    if (is_array($string)) {
      return $string;
    }
    return $this->upstreamForActiveTheme()->applyTypography($string, $arguments, $useDefaults);
  }

  private function upstreamForActiveTheme(): UpstreamTypographyExtension {
    $themeName = $this->themeManager->getActiveTheme()->getName();
    if (!isset($this->cache[$themeName])) {
      $path = $this->extensionPathResolver->getPath('theme', $themeName) . '/static/typography.yml';
      $config = file_exists($path) ? ((array) Yaml::parse(file_get_contents($path) ?: '')) : [];
      $this->cache[$themeName] = new UpstreamTypographyExtension($config);
    }
    return $this->cache[$themeName];
  }
}
```

- [ ] **Step 2: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/src/Unit/Twig/TypographyExtensionTest.php`
Expected: `OK (5 tests, X assertions)` — all 5 tests pass.

If the YAML-config test (`testYamlConfigReachesUpstream`) fails because upstream 1.2.0 hasn't actually shipped the `string|array $config` constructor overload, switch the wrapper to write a temp YAML file and pass its path instead of an array. Note this in CHANGELOG.

- [ ] **Step 3: Run PHPStan**

Run: `vendor/bin/phpstan analyse src/Twig/TypographyExtension.php`
Expected: `[OK] No errors` (or matching whatever the project's baseline allows).

- [ ] **Step 4: Commit**

```bash
git add src/Twig/TypographyExtension.php tests/src/Unit/Twig/TypographyExtensionTest.php
git commit -m "feat: add Drupal wrapper for parisek/twig-typography

New Drupal\\custom_components\\Twig\\TypographyExtension resolves
the active theme's static/typography.yml, parses it once per theme,
and delegates to a cached upstream Parisek\\Twig\\TypographyExtension
instance. Drupal render arrays pass through unchanged.

Five unit tests cover: filter registration, render-array pass-through,
missing-YAML fallback to upstream defaults, YAML config delivery to
upstream, and per-theme caching."
```

---

## Task 4: Register the wrapper in services.yml

**Files:**
- Modify: `custom_components.services.yml`

- [ ] **Step 1: Add the new service registration**

Append to the existing services list (before or after any existing entry; put it next to `custom_components.twig_extension` for locality):

```yaml
  custom_components.typography_twig_extension:
    class: Drupal\custom_components\Twig\TypographyExtension
    arguments: ["@theme.manager", "@extension.path.resolver"]
    tags:
      - { name: twig.extension }
```

Final `custom_components.services.yml` should have both `custom_components.twig_extension` AND `custom_components.typography_twig_extension` registered as `twig.extension`-tagged services. (After Task 5 removes the `typography` filter from `TwigExtension`, the two won't collide.)

- [ ] **Step 2: Commit**

```bash
git add custom_components.services.yml
git commit -m "feat: register typography twig extension service"
```

---

## Task 5: Remove duplicated typography code from `TwigExtension.php`

**Files:**
- Modify: `src/TwigExtension.php`

- [ ] **Step 1: Remove the `typography` filter registration from `getFilters()`**

In `src/TwigExtension.php`, locate lines 69–73:
```php
      new TwigFilter(
        'typography',
        [$this, 'getTypography'],
        ['is_safe' => ['html']]
      ),
```

Delete those 5 lines (including the trailing comma). The resulting `getFilters()` should return four filters: `option_label`, `country_name`, `resizer`, `date`.

- [ ] **Step 2: Remove the three typography helper methods**

Delete lines 340–407 in `src/TwigExtension.php`, which contain:
- `public static function getTypography(...)` and its docblock
- `private static function getDefaults()` and its docblock
- `public static function getFilePath()` and its docblock

The class should end at the `mergeResizer()` closing brace, followed by `}` for the class itself.

- [ ] **Step 3: Remove now-unused `use` imports**

In `src/TwigExtension.php`, delete lines 9–11:
```php
use PHP_Typography\Settings;
use PHP_Typography\PHP_Typography;
use Symfony\Component\Yaml\Yaml;
```

- [ ] **Step 4: Run all tests to verify nothing in `TwigExtension` broke**

Run: `vendor/bin/phpunit tests/src/Unit/TwigExtensionTest.php`
Expected: All existing TwigExtension tests pass (they don't cover typography, so they shouldn't be affected).

- [ ] **Step 5: Run PHPStan on the whole module**

Run: `composer phpstan` (or `vendor/bin/phpstan analyse src tests`)
Expected: `[OK] No errors`.

If PHPStan complains about callsites of `TwigExtension::getTypography()` elsewhere in the repo, those are dead references and should be searched-and-removed. Run:
```bash
grep -rn "getTypography\|getFilePath\|getDefaults" src tests
```
Expected: No matches outside the just-deleted code.

- [ ] **Step 6: Commit**

```bash
git add src/TwigExtension.php
git commit -m "refactor: remove duplicated typography logic from TwigExtension

The |typography filter, getTypography(), getDefaults() and getFilePath()
all duplicated what parisek/twig-typography already does. The new
custom_components.typography_twig_extension service now provides the
filter; this commit removes the dead code and now-unused imports."
```

---

## Task 6: Refactor `FilterTypography` plugin to delegate

**Files:**
- Modify: `src/Plugin/Filter/FilterTypography.php`

- [ ] **Step 1: Replace the file contents with the delegating version**

```php
<?php

declare(strict_types=1);

namespace Drupal\custom_components\Plugin\Filter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\custom_components\Twig\TypographyExtension;
use Drupal\filter\FilterProcessResult;
use Drupal\filter\Plugin\FilterBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a filter to format typography.
 *
 * @Filter(
 *   id = "filter_typography",
 *   title = @Translation("Typography Filter"),
 *   description = @Translation("Help format typography"),
 *   type = Drupal\filter\Plugin\FilterInterface::TYPE_TRANSFORM_IRREVERSIBLE,
 * )
 */
class FilterTypography extends FilterBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly TypographyExtension $typography,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('custom_components.typography_twig_extension'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process($text, $langcode) {
    $text = $this->typography->applyTypography($text);

    $html_dom = Html::load($text);
    $blockquote = $html_dom->getElementsByTagName('blockquote');
    foreach ($blockquote as $b) {
      $classes = $b->getAttribute('class');
      $classes = (strlen($classes) > 0) ? explode(' ', $classes) : [];
      if (!in_array('blockquote', $classes, TRUE)) {
        $classes[] = 'blockquote';
      }
      $b->setAttribute('class', implode(' ', $classes));
    }

    $text = Html::serialize($html_dom);

    return (new FilterProcessResult($text))->setProcessedText($text);
  }

}
```

Note: `applyTypography()` returns `mixed`, but in this context `$text` is always a string (Drupal text-format plugins receive `$text: string`), so the call is type-safe.

- [ ] **Step 2: Run PHPStan**

Run: `composer phpstan`
Expected: `[OK] No errors`.

- [ ] **Step 3: Run the full unit test suite**

Run: `composer test` (or `vendor/bin/phpunit`)
Expected: All tests pass.

- [ ] **Step 4: Commit**

```bash
git add src/Plugin/Filter/FilterTypography.php
git commit -m "refactor: delegate FilterTypography to the new typography wrapper

FilterTypography previously duplicated PHP_Typography setup. It now
injects custom_components.typography_twig_extension via
ContainerFactoryPluginInterface and delegates to its applyTypography()
method. The Drupal-specific blockquote-class manipulation is preserved."
```

---

## Task 7: composer install + smoke verification

**Files:**
- Modify: `composer.lock` (auto-generated)

- [ ] **Step 1: Run composer update for the swapped dependency**

Run: `composer update parisek/twig-typography mundschenk-at/php-typography --with-dependencies`
Expected: `mundschenk-at/php-typography` stays in lock (as transitive dep of `parisek/twig-typography`), and `parisek/twig-typography 1.2.x` is added.

- [ ] **Step 2: Run `composer validate --strict`**

Run: `composer validate --strict`
Expected: `./composer.json is valid`

- [ ] **Step 3: Verify dependency tree**

Run: `composer why mundschenk-at/php-typography`
Expected: Output shows `parisek/twig-typography` requires it (transitive), with no direct requirement from `parisek/custom-components`.

- [ ] **Step 4: Final full-suite run**

Run: `composer test && composer phpstan`
Expected: All tests pass, PHPStan reports no errors.

- [ ] **Step 5: Commit the lock file**

```bash
git add composer.lock
git commit -m "deps: update composer.lock for parisek/twig-typography swap"
```

---

## Task 8: Update CHANGELOG and prepare for PR

**Files:**
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Read the current CHANGELOG to see format**

Run: `head -30 CHANGELOG.md`

Adapt the entry below to match whatever Keep-a-Changelog or freeform format is already used.

- [ ] **Step 2: Add an entry under Unreleased / next version**

Append after the most recent header:
```markdown
### Changed
- Typography filter (`|typography` Twig filter + `filter_typography` text-format plugin) now delegates to `parisek/twig-typography ^1.2` instead of duplicating its logic. No behaviour change for callers — same filter name, same YAML path (`{active_theme}/static/typography.yml`), same defaults.
- Direct dependency on `mundschenk-at/php-typography` removed; it is now pulled transitively via `parisek/twig-typography`.

### Added
- `Drupal\custom_components\Twig\TypographyExtension` — thin Drupal wrapper that resolves the active theme path, caches the parsed config per theme, and delegates to the upstream extension. Pass-through for Drupal render arrays.
```

- [ ] **Step 3: Commit**

```bash
git add CHANGELOG.md
git commit -m "docs: changelog entry for twig-typography integration"
```

- [ ] **Step 4: Push and (optionally) open a PR**

```bash
git push -u origin feat/twig-typography-integration
```

Then via GitHub UI or `gh pr create`, open the PR. Body should reference this plan document and the upstream `parisek/twig-typography v1.2.0` release.

---

## Self-Review

**Spec coverage check (against the conversation):**
- [x] Drop direct `mundschenk-at/php-typography` requirement → Task 1
- [x] Depend on `parisek/twig-typography ^1.2` → Task 1
- [x] Refactor `TwigExtension::getTypography()` to delegate → Task 5 (removes it; delegation lives in the new wrapper from Task 3)
- [x] Refactor `FilterTypography` to delegate → Task 6
- [x] Wire via Drupal services container → Task 4
- [x] Preserve theme-resolved YAML behaviour → Task 3 implementation
- [x] Preserve render-array pass-through (Drupal-specific) → Task 3 implementation + test
- [x] Don't add anything to the upstream PR → reflected in plan scope (no upstream changes)
- [x] Tests for new code → Task 2 (5 tests)
- [x] Static analysis green → Tasks 5, 6, 7
- [x] Existing tests still pass → verified in Tasks 5, 6, 7

**Placeholder scan:** No TBDs, no "add error handling", no "similar to Task N", no missing code blocks.

**Type consistency:**
- `TypographyExtension::applyTypography(mixed $string, array $arguments = [], bool $useDefaults = TRUE): mixed` matches the upstream `Parisek\Twig\TypographyExtension::applyTypography($string, array $arguments = [], $use_defaults = TRUE)`.
- Constructor signature `(ThemeManagerInterface, ExtensionPathResolver)` matches the services.yml arguments `[@theme.manager, @extension.path.resolver]`.
- `FilterTypography::__construct(array, $plugin_id, $plugin_definition, TypographyExtension)` matches the `create()` factory's `new self(...)` call.
- Service name `custom_components.typography_twig_extension` used consistently in services.yml + FilterTypography::create + (none other).

---

## Open Risks

1. **Upstream 1.2.0 tag must exist before Task 7 runs.** Mitigation: Task 1 Step 3 explicitly checks.
2. **`string|array $config` constructor must be the additive overload from the 1.2.0 PR.** If the upstream PR shifts in scope before merge, the YAML-config test in Task 2 may need to switch to writing a temp file and passing a path. Mitigation noted in Task 3 Step 2.
3. **`FilterTypography` removes the `Settings` + `PHP_Typography` direct usage**, including the `$use_defaults = TRUE` branch. The wrapper's `applyTypography()` defaults to `$useDefaults = TRUE`, so behaviour matches. Verify by manually rendering a node with the typography text format enabled, post-merge — there's no Kernel test for this filter today.
