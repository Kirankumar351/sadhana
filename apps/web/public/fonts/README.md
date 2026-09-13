# Fonts — required before launch

Two subset `.woff2` files belong here. They are **not** in the repository because
they are build artefacts, and because subsetting is something you do once and check.

| File | Source | Subset to |
|---|---|---|
| `noto-sans-telugu-subset.woff2` | [Noto Sans Telugu](https://fonts.google.com/noto/specimen/Noto+Sans+Telugu) | `U+0C00-0C7F, U+200C-200D` |
| `gabarito-subset.woff2` | [Gabarito](https://fonts.google.com/specimen/Gabarito) | `U+0000-00FF, U+2000-206F, U+20B9` |

## Why subset, and why self-host

The full Noto Sans Telugu is **over 300 KB**. The subset is around **60 KB**. On a 3G
connection that difference is roughly two seconds of blank text on every first visit,
and it is paid for out of a limited data pack.

Self-hosted rather than loaded from Google Fonts because a third-party font adds a DNS
lookup plus a TLS handshake to a resource needed on *every single page*. On the networks
our users are actually on, that lookup alone can cost hundreds of milliseconds.

`U+200C-200D` in the Telugu range is not optional — those are the zero-width
non-joiner and joiner, and Telugu conjuncts render incorrectly without them.

## Generating them

```bash
pip install fonttools brotli

pyftsubset NotoSansTelugu-Regular.ttf \
  --unicodes="U+0C00-0C7F,U+200C-200D" \
  --flavor=woff2 --layout-features='*' \
  --output-file=noto-sans-telugu-subset.woff2

pyftsubset Gabarito-Variable.ttf \
  --unicodes="U+0000-00FF,U+2000-206F,U+20B9" \
  --flavor=woff2 --layout-features='*' \
  --output-file=gabarito-subset.woff2
```

Keep `--layout-features='*'`. Dropping them strips the GSUB rules that build Telugu
conjuncts, and the text will render as visibly wrong letter clusters rather than
failing in a way anyone notices in review.

`U+20B9` is the rupee sign. Every price in the product uses it.

## Until they are added

`font-display: swap` means the page renders immediately in the fallback stack, so the
product works — Telugu simply renders in whatever the device has. That is acceptable in
development and **not** acceptable at launch: the fallback on a budget Android is often
a font with poor Telugu hinting, which is exactly the audience this product is for.
