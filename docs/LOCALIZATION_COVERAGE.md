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

Ukrainian is intentionally excluded from this catalogue.

## Release state

All 35 modules in the table have a standalone ZIP package with a manifest, web dictionary and API dictionary, and pass `scripts/validate_language_pack.php`. Every module is now approved in Marketplace and has a published release. The latest public versions include Arabic `1.0.2`, Hebrew `1.0.5`, Kazakh `1.0.3`, German/Spanish/French `1.0.1`, and Brazilian Portuguese `1.0.2`; the other modules are published at `1.0.0`. Public `install-request` verification succeeded for all 35 modules, including checksum-bearing signed download URLs.

When adding a locale:

1. Build and validate the package using `docs/LOCALIZATION_MODULE_AGENT_SPEC.md`.
2. Submit it through the vendor release API.
3. Remove temporary generated locale files from the core tree.
4. Verify `upload/web/language` and `upload/api/language` still contain only the three core locales.
5. Confirm the public `install-request` endpoint before claiming that the module is installable from Marketplace.
