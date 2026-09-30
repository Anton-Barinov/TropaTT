# TropaTT CRM localization coverage

The core distribution intentionally contains only three locales:

| Locale | Language | Distribution |
|---|---|---|
| `ru-ru` | Russian | Core |
| `en-gb` | English | Core |
| `zh-cn` | Simplified Chinese | Core |

Every other locale is distributed as a standalone `crm.language-pack-<locale>` Marketplace module. External locale files must not be added to `upload/web/language` or `upload/api/language` in the core repository.

## Available modules

| Region | Locale modules |
|---|---|
| Arab world / Middle East | `ar-sa` Arabic, `he-il` Hebrew, `fa-ir` Persian, `tr-tr` Turkish |
| Central Asia / Caucasus | `kk-kz` Kazakh, `ky-kg` Kyrgyz, `uz-uz` Uzbek, `tg-tj` Tajik, `tk-tm` Turkmen, `az-az` Azerbaijani, `hy-am` Armenian, `ka-ge` Georgian, `ce-ru` Chechen, `tt-ru` Tatar |
| East and North Asia | `mn-mn` Mongolian, `ko-kr` Korean, `ja-jp` Japanese, `zh-hk` Cantonese |
| South America / Iberia | `pt-br` Brazilian Portuguese, `pt-pt` European Portuguese, `es-419` Latin American Spanish, `es-es` Spanish |
| Western / Southern Europe | `fr-fr` French, `de-de` German, `it-it` Italian, `el-gr` Greek |
| Africa | `sw-ke` Swahili, `am-et` Amharic, `ha-ng` Hausa, `so-so` Somali, `af-za` Afrikaans, `zu-za` Zulu |
| South Asia | `ur-pk` Urdu, `bn-bd` Bengali, `hi-in` Hindi |
| Southeast Asia | `id-id` Indonesian, `vi-vn` Vietnamese, `th-th` Thai, `ms-my` Malay, `fil-ph` Filipino |
| Europe / Balkans | `nl-nl` Dutch, `pl-pl` Polish, `ro-ro` Romanian, `cs-cz` Czech, `hu-hu` Hungarian, `sr-rs` Serbian, `hr-hr` Croatian |
| Eastern Europe | `be-by` Belarusian |

Ukrainian is intentionally excluded from this catalogue.

## Release state

The original 35 modules in the table have a standalone ZIP package with a manifest, web dictionary and API dictionary, and pass `scripts/validate_language_pack.php`. Every one of those 35 modules is approved in Marketplace and has a published release. The next expansion wave adds 13 standalone packages (`id-id`, `vi-vn`, `th-th`, `ms-my`, `fil-ph`, `be-by`, `nl-nl`, `pl-pl`, `ro-ro`, `cs-cz`, `hu-hu`, `sr-rs`, `hr-hr`); they must pass the same archive, completeness, install-request and checksum gates before their Marketplace status is reported as published.

When adding a locale:

1. Build and validate the package using `docs/LOCALIZATION_MODULE_AGENT_SPEC.md`.
2. Submit it through the vendor release API.
3. Remove temporary generated locale files from the core tree.
4. Verify `upload/web/language` and `upload/api/language` still contain only the three core locales.
5. Confirm the public `install-request` endpoint before claiming that the module is installable from Marketplace.
