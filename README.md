# Staff Rota Coverage Analyser

Built by [Phil Atkins](https://phillipatkins.co.uk)

---

A care home manager I know was spending two hours every Sunday evening manually checking her staff rota in Excel — trying to spot coverage gaps before the week started. She'd been doing it for two years. It still occasionally failed.

I built a rota analyser. Drop in the spreadsheet, and in seconds it flags every zero-cover shift, every understaffed shift, every person heading for a Working Time Directive breach, and shows a colour-coded heatmap of the whole month.

The zero-cover gaps she'd have found Monday morning — she now finds Sunday afternoon.

---

## What it does

- Checks every shift across 4 weeks against minimum staffing requirement
- Flags **zero-cover shifts** (red) — nobody assigned
- Flags **understaffed shifts** (amber) — below your minimum
- Calculates total hours per staff member
- Flags anyone heading for a **Working Time Directive breach** (>48 hours in a week)
- Outputs a colour-coded **heatmap** of the full rota

---

## Setup

```bash
pip install -r requirements.txt
```

---

## Usage

```bash
# Run against the included sample rota
python analyser.py sample_rota.csv

# Specify a different minimum staffing level
python analyser.py my_rota.csv --min 3

# Default minimum is 2 staff per shift
python analyser.py
```

---

## CSV format

One row per staff member. First column is `Staff` (name). Every other column is a shift, named `Week{N}_{Day}_{Type}`:

```
Staff,Week1_Mon_Early,Week1_Mon_Late,Week1_Mon_Night,...
Alice,Alice,,Alice,...
Beth,,Beth,,...
```

- Put the staff member's name in the cell if they're working that shift
- Leave it empty if they're not
- 4 weeks, 7 days, 3 shifts = 84 shift columns total

A template matches the included `sample_rota.csv`.

---

## Sample data

The included `sample_rota.csv` has 12 staff across 4 weeks with:
- 2 zero-cover shifts (nobody assigned)
- Several understaffed shifts
- All 12 staff members hitting overtime in week 1

Run `python analyser.py sample_rota.csv` to see it in action.

---

## Notes

- Working Time Directive limit is 48 hours per week (UK law)
- Each shift counts as 8 hours — adjust `HOURS_PER_SHIFT` in the script if yours differ
- The tool doesn't modify your CSV — it's read-only

---

MIT License — Phil Atkins 2026 — [phillipatkins.co.uk](https://phillipatkins.co.uk)
