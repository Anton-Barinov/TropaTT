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

## Marketplace naming convention

All language-pack listings use the same title template:

```text
TropaTT CRM Language Pack — <native language name>
```

The `title_en` field follows the same structure with the English language name,
and `title_zh` uses `TropaTT CRM 语言包 — <native language name>`. On 30 September
2026 this template was applied to all 48 published external language modules;
the module codes, release versions and package manifests were not changed.

## Release state

All **48 external modules** in the table have standalone ZIP packages with manifests, web dictionaries and API dictionaries. The original 35 modules and the 13-module expansion wave (`id-id`, `vi-vn`, `th-th`, `ms-my`, `fil-ph`, `be-by`, `nl-nl`, `pl-pl`, `ro-ro`, `cs-cz`, `hu-hu`, `sr-rs`, `hr-hr`) are approved and published in Marketplace. Each new release passed the local archive validator, the Marketplace AST/security pipeline, public module-page checks, and signed `install-request` download verification with a matching Marketplace SHA-256.

When adding a locale:

1. Build and validate the package using `docs/LOCALIZATION_MODULE_AGENT_SPEC.md`.
2. Submit it through the vendor release API.
3. Remove temporary generated locale files from the core tree.
4. Verify `upload/web/language` and `upload/api/language` still contain only the three core locales.
5. Confirm the public `install-request` endpoint before claiming that the module is installable from Marketplace.
