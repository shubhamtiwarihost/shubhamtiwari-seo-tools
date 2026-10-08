# Readability analysis

DumpSEO checks how easy a post is to read. Like the SEO checks, each finding has a status, severity, message, recommendation and measured values, and there is no overall score. The rules were written for DumpSEO; they use widely known writing guidance and the public Flesch formula.

## Checks

| ID | Languages | Checks | Pass | Otherwise |
|---|---|---|---|---|
| `sentence_length` | all | Share of sentences over 20 words (texts of 50+ words) | ≤ 25% | warning ≤ 40%, error above |
| `paragraph_length` | all | Paragraphs over 150 words | none | warning with count |
| `subheading_distribution` | all | Subheadings in texts over 300 words; no section over 300 words | spread well | warning |
| `passive_voice` | English | Share of sentences with a passive-voice pattern | ≤ 10% | warning |
| `transition_words` | English | Share of sentences with a transition word or phrase (5+ sentences) | ≥ 30% | warning |
| `reading_ease` | English | Flesch reading ease (100+ words) | ≥ 60 | warning, with a label from "fairly difficult" to "very difficult" |

## How the text is read

- **Sentences** come from paragraphs and list items; headings are not sentences. A sentence ends at `.`, `!`, `?` or `…` followed by a capital letter, digit or quote. Periods after common abbreviations (Dr., e.g., etc.), initials (J. R.) and inside numbers (3.5) do not end sentences.
- **Paragraphs** are `<p>` elements, or blocks separated by blank lines in text without `<p>` tags.
- **Passive voice** is detected by pattern: a form of *be* or *get*, optionally *not* or an *-ly* adverb, then a past participle (*-ed* word or a common irregular one such as *written*). Common adjectives like *tired* or *based* are excluded, but it is still an indicator, not a grammar check.
- **Transition words** are matched as whole words or phrases from a list of about 100 (*however*, *for example*, *as a result*…). Add your own with the `dumpseo_transition_words` filter.
- **Flesch reading ease** = 206.835 − 1.015 × (words ÷ sentences) − 84.6 × (syllables ÷ words). Syllables are estimated from vowel groups with corrections for silent endings, so scores are approximate. 60–70 is plain English; technical writing often scores lower, and that can be fine for its readers.

## Languages

The content language is the site language (`get_locale()`). Multilingual plugins can give each post its own language with the `dumpseo_content_locale` filter. For non-English content only the language-independent checks run. Other languages are planned.
