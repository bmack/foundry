# TYPO3 foundry

**A new TYPO3 project in a few minutes. Clone, answer a few questions, done.**

You know the feeling: new client, new site, and the first hour goes to setting things up
instead of building. foundry does that hour for you. It hands you a running TYPO3 with
your own site package, a first page and a login, and then it gets out of the way and
deletes itself. What you keep is a normal TYPO3 project. No lock-in, no leftovers.

> Looking to work on TYPO3 Core itself? That is what its sibling
> [tryout](https://github.com/bmack/tryout) is for. foundry is for building sites.

## Let's go

You need [DDEV](https://ddev.readthedocs.io/en/stable/) (which needs Docker) and Git. Then:

```bash
git clone --depth=1 https://github.com/bmack/foundry.git my-site
cd my-site
ddev foundry setup
```

Grab a coffee (the first run downloads a few things). When it is done:

| | |
| --- | --- |
| **Your site** | `https://my-site.ddev.site/` |
| **Backend** | `https://my-site.ddev.site/typo3/` |
| **Login** | `admin` / `Password.1` (fine for your laptop, not for the internet) |

`my-site` is the name of the folder you cloned into. Pick a good one, it becomes the address.

## The four questions

foundry asks these, and every one has a default, so you can just press Enter:

| Question | What it decides | Default |
| --- | --- | --- |
| **Project** | The address (`my-site.ddev.site`) and the name of your site package | the folder name |
| **Vendor** | Who "owns" the code: `acme/my-site`, `Acme\MySite` | the project name |
| **TYPO3 version** | `14` (stable) or `15` (the bleeding edge, `dev-main`) | `14` |
| **Base** | What your site is built on, see below | `custom` |

### Don't want to be asked?

Say it up front, and foundry only asks about the rest:

```bash
ddev foundry setup --vendor=acme --version=15   # answer two, get asked two
ddev foundry setup --yes                        # answer nothing, take every default
```

Prefer environment variables? Same idea:

| Question | Flag | Environment variable |
| --- | --- | --- |
| Project | `--project=` | `FOUNDRY_PROJECT` |
| Vendor | `--vendor=` | `FOUNDRY_VENDOR` |
| TYPO3 version | `--version=` | `FOUNDRY_VERSION` |
| Base | `--base=` | `FOUNDRY_BASE` |

A flag beats an environment variable, which beats the default. Running in a script or CI
without a terminal? Then `--yes` is on automatically.

## Pick a base

The base is your starting point. Every base except `none` creates **your own site package**
in `packages/<project>/`, so you always have a place to put your work.

| Base | Choose it when... |
| --- | --- |
| `custom` | You want a clean, simple page layout you can make your own: header, navigation, content, footer. Styled with [Soul](#about-the-look-of-custom). **This is the default.** |
| `fsc` | You want the same structure with no styling at all, just Fluid Styled Content |
| `camino` | You want TYPO3's Camino theme, demo content included |
| `bootstrap` | You like the Bootstrap Package (TYPO3 14 only, it is not ready for 15 yet) |
| `empty` | You want an empty site package and will build everything yourself |
| `none` | You want plain TYPO3 and no site package at all |

With `custom` and `fsc` you also get two sample pages, About and Contact, so the menu has
something to show on day one.

Every system extension is installed as well, so nothing is missing later.
(`adminpanel` and `lowlevel` are development-only.)

## Where do I change things?

Open `packages/my-site/`. With `custom` or `fsc` it looks like this:

```text
packages/my-site/
├── Configuration/Sets/Main/   # TypoScript, page TSconfig and settings for your site
└── Resources/
    ├── Private/
    │   ├── Pages/             # One template per page type
    │   ├── Layouts/           # The frame around every page
    │   ├── Partials/          # Reusable bits: header, footer, menu
    │   └── Content/           # Your versions of content element templates
    └── Public/                # CSS, fonts, images
```

With `custom`: want to change the header? That is `Partials/Header.html`. The footer?
`Partials/Footer.html`. Want your own version of the text element? Copy its template from
`EXT:fluid_styled_content` into `Content/` and edit it.

## What just happened?

That single command was busy. In order, it:

1. Named your DDEV project after your folder and picked the right PHP for your TYPO3 version.
2. Wrote your `composer.json`, your site package, a project `README.md` and a `.gitignore`.
3. Ran `composer install` and set TYPO3 up with a first site at your address.
4. Connected that site to your site package and added the sample pages.
5. **Deleted foundry** and started a fresh Git repository for you.

So the folder you have now is just your project. Commit `composer.lock` and `config/sites/`,
push it somewhere, and carry on. (Want to keep the history of the clone? Add `--keep-git`.)

## About the look of `custom`

The `custom` layout speaks the class language of the
[Soul Design System](https://github.com/TYPO3/soul-design-system). We copied in its
stylesheet and fonts, so there is nothing to build. It even switches between light and dark
with your reader's system setting.

Two things to know:

- **Make it yours.** Soul's logos and artwork are deliberately not included. Treat the look
  as a starting point and adjust it in `Resources/Public/Css/layout.css`.
- **Don't pretend to be TYPO3.** Soul is made for community projects and is still
  experimental. A site built on it must not suggest that it is an official TYPO3 site.

## For the curious

<details>
<summary>How is foundry organised inside?</summary>

```text
foundry/
├── .ddev/
│   ├── commands/host/foundry     # The ddev foundry command (runs on your machine)
│   └── config.yaml               # DDEV settings (PHP, database, environment)
├── foundry/                      # Everything in here is deleted after setup
│   ├── generate.php              # Builds your project from the templates
│   ├── versions/                 # 14.json, 15.json: version and extension lists
│   ├── bases/                    # One folder per base
│   └── common/                   # Files every project gets
├── config/system/additional.php  # Mail, graphics and host settings for DDEV
└── packages/                     # Where your site package will live
```

</details>

<details>
<summary>How do I add a base or change the extensions?</summary>

The extensions installed for each TYPO3 version are listed in `foundry/versions/`.

A base is a folder in `foundry/bases/` with a `base.json` (its label, the Composer packages
it needs and the Sets it depends on) and, if you like, a `package/` folder of files that get
copied into the site package. Files ending in `.tpl` are filled in first, using
placeholders such as `{{ vendor }}`, `{{ project }}`, `{{ extKey }}` and `{{ title }}`.

</details>

<details>
<summary>What do I need on my machine?</summary>

- [DDEV](https://ddev.readthedocs.io/en/stable/) v1.24+
- Docker Desktop or Colima
- Git
- A `bash` shell. `ddev foundry` runs on your machine, not in the container. On Windows,
  that is Git Bash, which comes with Git for Windows.

</details>

## Contribute

foundry lives at [github.com/bmack/foundry](https://github.com/bmack/foundry). A new base,
a better default, a typo: pull requests and issues are welcome.

## License

MIT, see [LICENSE](LICENSE).
