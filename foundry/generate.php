<?php

declare(strict_types=1);

/*
 * Renders a new TYPO3 project from the foundry templates.
 *
 *   php foundry/generate.php --vendor=acme --project=my-site --version=14 --base=custom
 *   php foundry/generate.php --finalize --base-url=https://my-site.ddev.site/   (after `typo3 setup`)
 *   php foundry/generate.php --seed-sql      (SQL for a few sample pages, empty for bases that bring their own)
 *   php foundry/generate.php --print-title   (the project title generated earlier)
 *
 * Runs inside the DDEV web container; needs nothing but PHP. --root writes to
 * another directory, which allows a dry run on the host.
 */

const NAME_PATTERN = '/^[a-z][a-z0-9]*(-[a-z0-9]+)*$/';
const VERSIONS = ['14', '15'];

main();

function main(): void
{
    $options = getopt('', ['vendor:', 'project:', 'version:', 'base:', 'title:', 'root:', 'base-url:', 'finalize', 'print-title', 'seed-sql']);
    $root = rtrim((string)($options['root'] ?? dirname(__DIR__)), '/');

    if (isset($options['print-title'])) {
        echo readState($root)['title'];
        return;
    }
    if (isset($options['seed-sql'])) {
        echo seedSql(readState($root));
        return;
    }
    if (isset($options['finalize'])) {
        finalize($root, (string)($options['base-url'] ?? ''));
        return;
    }
    generate($root, $options);
}

function generate(string $root, array $options): void
{
    $vendor = requireName($options, 'vendor');
    $project = requireName($options, 'project');
    $version = (string)($options['version'] ?? '');
    if (!in_array($version, VERSIONS, true)) {
        fail('--version must be one of: ' . implode(', ', VERSIONS));
    }
    $baseName = (string)($options['base'] ?? '');
    $base = loadBase($baseName);
    if (!in_array($version, array_map('strval', $base['versions']), true)) {
        fail(sprintf('Base "%s" does not support TYPO3 %s', $baseName, $version));
    }

    $versionConfig = readJson(__DIR__ . '/versions/' . $version . '.json');
    $constraint = $versionConfig['constraint'];
    $title = (string)($options['title'] ?? ucwords(str_replace('-', ' ', $project)));

    $vars = [
        'vendor' => $vendor,
        'project' => $project,
        'title' => $title,
        'composerName' => $vendor . '/' . $project,
        'extKey' => str_replace('-', '_', $project),
        'namespace' => studly($vendor) . '\\' . studly($project),
        'typo3Constraint' => $constraint,
        'year' => date('Y'),
    ];

    // Root composer.json
    $require = [];
    foreach ($versionConfig['packages'] as $package) {
        $require['typo3/cms-' . $package] = $constraint;
    }
    $requireDev = [];
    foreach ($versionConfig['dev'] as $package) {
        $requireDev['typo3/cms-' . $package] = $constraint;
    }
    if ($base['package']) {
        $require[$vars['composerName']] = '@dev';
    }
    ksort($require);
    ksort($requireDev);

    $composer = [
        'name' => $vars['composerName'] . '-project',
        'description' => 'TYPO3 project ' . $title,
        'type' => 'project',
        'license' => 'proprietary',
        'repositories' => [['type' => 'path', 'url' => 'packages/*']],
        'require' => $require,
        'require-dev' => $requireDev,
        'config' => [
            'allow-plugins' => [
                'typo3/class-alias-loader' => true,
                'typo3/cms-composer-installers' => true,
            ],
            'sort-packages' => true,
        ],
    ];
    if (($versionConfig['minimum-stability'] ?? 'stable') !== 'stable') {
        $composer['minimum-stability'] = $versionConfig['minimum-stability'];
    }
    $composer['prefer-stable'] = true;
    writeJson($root . '/composer.json', $composer);

    // Project files
    writeFile($root . '/.gitignore', (string)file_get_contents(__DIR__ . '/common/project.gitignore'));
    writeFile($root . '/README.md', render((string)file_get_contents(__DIR__ . '/common/README.md.tpl'), $vars + [
        'baseLabel' => $base['label'],
        'typo3Label' => $versionConfig['label'],
    ]));

    // Site package
    if ($base['package']) {
        $target = $root . '/packages/' . $project;
        foreach (array_merge([__DIR__ . '/common/package'], $base['dirs']) as $dir) {
            copyTree($dir, $target, $vars);
        }
        writeJson($target . '/composer.json', sitePackageComposer($vars, $base, $version, $constraint));
        writeFile($target . '/Configuration/Sets/Main/config.yaml', setConfig($vars, $base['setDependencies']));
    }

    writeFile(
        statePath($root),
        json_encode(['title' => $title, 'package' => $base['package'], 'seed' => $base['seed'], 'set' => $vars['composerName']], JSON_PRETTY_PRINT) . "\n"
    );

    echo sprintf("Generated %s (TYPO3 %s, base %s)\n", $vars['composerName'], $version, $baseName);
}

/**
 * The site created by `typo3 setup --create-site` gets the site package's set
 * as its dependency, so the package is what renders and configures the site.
 */
function finalize(string $root, string $baseUrl): void
{
    $state = readState($root);
    if (!$state['package']) {
        return;
    }
    $configs = glob($root . '/config/sites/*/config.yaml') ?: [];
    if ($configs === []) {
        fail('No site configuration found in config/sites/');
    }
    $autoload = $root . '/vendor/autoload.php';
    if (!is_file($autoload)) {
        fail('Run composer install before --finalize');
    }
    require_once $autoload;
    foreach ($configs as $file) {
        // `typo3 setup` lists Fluid Styled Content; the site package's set replaces it.
        $config = \Symfony\Component\Yaml\Yaml::parseFile($file);
        $config['dependencies'] = [$state['set']];
        $config['websiteTitle'] ??= $state['title'];
        if ($baseUrl !== '') {
            // Camino imports its own site with a relative base
            $config['base'] = $baseUrl;
        }
        writeFile($file, \Symfony\Component\Yaml\Yaml::dump($config, 99, 2));

        // It also writes a bare PAGE with a TYPO3 logo next to config.yaml. Site
        // TypoScript is loaded after the sets, so it would replace the package's rendering.
        $siteTypoScript = dirname($file) . '/setup.typoscript';
        if (is_file($siteTypoScript) && str_contains((string)file_get_contents($siteTypoScript), 'max-width: 800px')) {
            unlink($siteTypoScript);
        }
    }
    echo "Site configuration now depends on " . $state['set'] . "\n";
}

/**
 * Sample pages for the bases that render the site themselves, so a new project
 * shows a navigation and real content instead of a single welcome text. The
 * page tree of a fresh `typo3 setup --create-site` holds the root page (uid 1)
 * and its welcome text, nothing else.
 */
function seedSql(array $state): string
{
    if (!$state['seed']) {
        return '';
    }
    $q = static fn(string $value): string => "'" . str_replace(["\\", "'"], ["\\\\", "''"], $value) . "'";
    $title = $state['title'];
    $now = time();

    // The root page and the admin are looked up, not assumed to have uid 1.
    $sql = "SET @root := (SELECT uid FROM pages WHERE pid = 0 AND deleted = 0 ORDER BY sorting, uid LIMIT 1);\n";
    $sql .= "SET @admin := COALESCE((SELECT uid FROM be_users WHERE admin = 1 AND deleted = 0 ORDER BY uid LIMIT 1), 1);\n";
    $sql .= sprintf(
        "UPDATE tt_content SET header = %s, bodytext = %s WHERE pid = @root;\n",
        $q('Welcome to ' . $title),
        $q('<p>This site was created with foundry. Edit the pages in the TYPO3 backend; the page layout lives in the site package under <code>Resources/Private/Pages/</code> (content element templates: <code>Resources/Private/Content/</code>).</p>')
    );
    $pages = [
        ['About', '/about', 256, 'About ' . $title, '<p>Tell visitors who is behind this site and what it is for.</p>'],
        ['Contact', '/contact', 512, 'Get in touch', '<p>Add your address, a contact form or a mail link here.</p>'],
    ];
    foreach ($pages as [$name, $slug, $sorting, $header, $text]) {
        $sql .= sprintf(
            "INSERT INTO pages (pid, sorting, title, slug, doktype, crdate, tstamp, perms_userid, perms_user, perms_group) VALUES (@root, %d, %s, %s, 1, %d, %d, @admin, 31, 27);\n",
            $sorting, $q($name), $q($slug), $now, $now
        );
        $sql .= sprintf(
            "INSERT INTO tt_content (pid, sorting, CType, colPos, header, bodytext, crdate, tstamp) VALUES (LAST_INSERT_ID(), 256, 'text', 0, %s, %s, %d, %d);\n",
            $q($header), $q($text), $now, $now
        );
    }
    return $sql;
}

function sitePackageComposer(array $vars, array $base, string $version, string $constraint): array
{
    $require = ['typo3/cms-core' => $constraint];
    foreach ($base['requires'] as $name => $value) {
        $require[$name] = $value === 'typo3' ? $constraint : ($value[$version] ?? fail('No constraint for ' . $name));
    }
    ksort($require);

    return [
        'name' => $vars['composerName'],
        'type' => 'typo3-cms-extension',
        'description' => 'Site package for ' . $vars['title'],
        'license' => 'proprietary',
        'require' => $require,
        'autoload' => ['psr-4' => [$vars['namespace'] . '\\' => 'Classes/']],
        'extra' => ['typo3/cms' => ['extension-key' => $vars['extKey']]],
    ];
}

function setConfig(array $vars, array $dependencies): string
{
    $yaml = 'name: ' . $vars['composerName'] . "\n";
    $yaml .= "label: '" . str_replace("'", "''", $vars['title']) . "'\n";
    if ($dependencies !== []) {
        $yaml .= "dependencies:\n";
        foreach ($dependencies as $dependency) {
            $yaml .= '  - ' . $dependency . "\n";
        }
    }
    return $yaml;
}

/**
 * A base lists the template directories it is built from (parents first), the
 * sets its site set depends on and the Composer packages its site package needs.
 */
function loadBase(string $name): array
{
    if (!preg_match(NAME_PATTERN, $name) || !is_file(__DIR__ . '/bases/' . $name . '/base.json')) {
        fail('Unknown base: ' . $name);
    }
    $dir = __DIR__ . '/bases/' . $name;
    $definition = readJson($dir . '/base.json');

    $base = [
        'label' => $definition['label'],
        'package' => $definition['package'] ?? true,
        'seed' => $definition['seed'] ?? false,
        'versions' => $definition['versions'] ?? VERSIONS,
        'setDependencies' => $definition['setDependencies'] ?? [],
        'requires' => $definition['requires'] ?? [],
        'dirs' => [],
    ];
    if (isset($definition['extends'])) {
        $parent = loadBase($definition['extends']);
        $base['dirs'] = $parent['dirs'];
        $base['seed'] = $definition['seed'] ?? $parent['seed'];
        $base['requires'] = $definition['requires'] ?? $parent['requires'];
        if (!isset($definition['setDependencies'])) {
            $base['setDependencies'] = $parent['setDependencies'];
        }
    }
    if (is_dir($dir . '/package')) {
        $base['dirs'][] = $dir . '/package';
    }
    return $base;
}

/** Copies a directory tree; files ending in .tpl are rendered and lose the suffix. */
function copyTree(string $from, string $to, array $vars): void
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($iterator as $item) {
        $relative = substr($item->getPathname(), strlen($from) + 1);
        $destination = $to . '/' . $relative;
        if ($item->isDir()) {
            if (!is_dir($destination)) {
                mkdir($destination, 0775, true);
            }
            continue;
        }
        if (str_ends_with($relative, '.tpl')) {
            writeFile(substr($destination, 0, -4), render((string)file_get_contents($item->getPathname()), $vars));
        } else {
            writeFile($destination, (string)file_get_contents($item->getPathname()));
        }
    }
}

function render(string $template, array $vars): string
{
    return (string)preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', static function (array $match) use ($vars): string {
        return $vars[$match[1]] ?? fail('Unknown template variable: ' . $match[1]);
    }, $template);
}

function statePath(string $root): string
{
    return $root . '/foundry/.state.json';
}

function readState(string $root): array
{
    $file = statePath($root);
    if (!is_file($file)) {
        fail('Nothing generated yet; run generate.php with --vendor, --project, --version and --base first');
    }
    return readJson($file);
}

function requireName(array $options, string $key): string
{
    $value = (string)($options[$key] ?? '');
    if (preg_match(NAME_PATTERN, $value) !== 1) {
        fail(sprintf('--%s must be lowercase letters, digits and dashes, starting with a letter', $key));
    }
    return $value;
}

function studly(string $value): string
{
    return str_replace(' ', '', ucwords(str_replace('-', ' ', $value)));
}

function readJson(string $file): array
{
    return json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
}

function writeJson(string $file, array $data): void
{
    writeFile($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n");
}

function writeFile(string $file, string $content): void
{
    if (!is_dir(dirname($file))) {
        mkdir(dirname($file), 0775, true);
    }
    file_put_contents($file, $content);
}

function fail(string $message): never
{
    fwrite(STDERR, 'Error: ' . $message . "\n");
    exit(1);
}
