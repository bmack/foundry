# foundry

Start a new TYPO3 project in minutes. Clone, run one command, answer a few questions.

```bash
git clone --depth=1 https://github.com/bmack/foundry.git my-site
cd my-site
ddev foundry setup
```

`setup` asks for:

| Question | Result |
| --- | --- |
| **Vendor** (`acme`) | Composer vendor and PHP namespace of your site package |
| **Project** (`my-site`) | Package name `acme/my-site`, DDEV project, and the URL `https://my-site.ddev.site/` |
| **TYPO3 version** | `14` (`^14.3`) or `15` (`dev-main`) |
| **Base** | see below |

Every question can be answered up front. The order of precedence is a flag, then an
environment variable, then the default:

| Question | Flag | Environment | Default |
| --- | --- | --- | --- |
| Project | `--project=` | `FOUNDRY_PROJECT` | the folder name, which is also the DDEV host name |
| Vendor | `--vendor=` | `FOUNDRY_VENDOR` | the project name |
| TYPO3 version | `--version=` | `FOUNDRY_VERSION` | `14` |
| Base | `--base=` | `FOUNDRY_BASE` | `custom` |

```bash
ddev foundry setup --yes                      # accept every default, no questions
ddev foundry setup --vendor=acme --version=15 # answer some, get asked the rest
FOUNDRY_BASE=fsc ddev foundry setup --yes
```

`--yes` is implied when there is no terminal (scripts, CI).

## Bases

| Base | What you get |
| --- | --- |
| `custom` | Site package with a basic HTML page layout (header, navigation, content, footer) styled with the [Soul](https://github.com/TYPO3/soul-design-system) class layer |
| `fsc` | Site package on Fluid Styled Content with a minimal page template |
| `camino` | Site package on the Camino theme |
| `bootstrap` | Site package on the Bootstrap Package (TYPO3 14 only) |
| `empty` | Site package with an empty Site Set |
| `none` | Plain TYPO3 without a site package |

Every base except `none` creates `packages/<project>/` as a Composer path package with a
Site Set. The site configuration created by `typo3 setup` depends on that set.

In `custom` and `fsc` the page templates live in `Resources/Private/Pages/`, `Layouts/` and
`Partials/` (rendered by `PAGEVIEW`), and content element overrides in `Resources/Private/Content/`.

All system extensions are installed (`adminpanel`, `lowlevel` and `styleguide` as dev
dependencies). The lists live in `foundry/versions/`.

## What setup does

1. Sets the DDEV project name and PHP version and starts DDEV.
2. Renders `composer.json`, the site package, `README.md` and `.gitignore`.
3. Runs `composer install` and `typo3 setup` with a site for `https://<project>.ddev.site/`.
4. Makes the site depend on the site package's set, runs `extension:setup` and, for `custom`
   and `fsc`, adds two sample pages (About, Contact) so the navigation has something to show.
   Camino and Bootstrap bring their own demo content.
5. Removes foundry itself (the `foundry/` directory and the command) and starts a fresh Git
   repository (`--keep-git` keeps the clone's history).

The result is an ordinary TYPO3 project: commit `composer.lock` and `config/sites/`.

## Soul in the `custom` base

The layout uses the class vocabulary of the [Soul Design System](https://github.com/TYPO3/soul-design-system)
(MIT). `soul.css` and its fonts are copied into `Resources/Public/Soul/`, pinned to a tag
(see `VERSION.txt`), and need no build step. Soul's brand assets are not included; treat the
look as a starting point and re-theme it through the tokens in `Resources/Public/Css/layout.css`.
Soul is experimental and dresses community projects, so a site built on it must not imply
that it is an official TYPO3 site.

## License

MIT
