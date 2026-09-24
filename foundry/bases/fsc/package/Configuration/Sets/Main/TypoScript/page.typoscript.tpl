page = PAGE
page {
    typeNum = 0

    10 = PAGEVIEW
    10 {
        paths {
            10 = EXT:{{ extKey }}/Resources/Private/
        }
        dataProcessing {
            10 = menu
            10 {
                levels = 1
                as = mainnavigation
            }
            20 = page-content
        }
    }

    meta {
        viewport = width=device-width, initial-scale=1
    }
}
