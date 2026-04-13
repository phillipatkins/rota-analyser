import csv
import sys
import argparse
from collections import defaultdict
from colorama import Fore, Style, init

HOURS_PER_SHIFT = 8
WTD_WEEKLY_LIMIT = 48  # hours per week


def load_rota(filepath):
    with open(filepath, newline='', encoding='utf-8') as f:
        reader = csv.DictReader(f)
        rows = list(reader)
    if not rows:
        print("CSV is empty.")
        sys.exit(1)
    return rows, reader.fieldnames


def parse_shifts(fieldnames):
    """Return list of shift column names (everything except 'Staff')."""
    return [f for f in fieldnames if f != 'Staff']


def analyse(rows, shift_cols, minimum_staff):
    # shift_coverage[shift] = list of staff names assigned
    shift_coverage = defaultdict(list)
    staff_hours = defaultdict(int)
    staff_weekly = defaultdict(lambda: defaultdict(int))

    for row in rows:
        name = row.get('Staff', '').strip()
        if not name:
            continue
        for shift in shift_cols:
            val = row.get(shift, '').strip()
            if val and val.lower() not in ('', '0', 'off', '-'):
                shift_coverage[shift].append(name)
                staff_hours[name] += HOURS_PER_SHIFT

                # Parse week number from shift name (e.g. "Week1_Mon_Early" -> week 1)
                week_num = 1
                if shift.startswith('Week'):
                    try:
                        week_num = int(shift[4])
                    except (IndexError, ValueError):
                        pass
                staff_weekly[name][week_num] += HOURS_PER_SHIFT

    # Find problems
    zero_cover = []
    understaffed = []

    for shift in shift_cols:
        count = len(shift_coverage[shift])
        if count == 0:
            zero_cover.append(shift)
        elif count < minimum_staff:
            understaffed.append((shift, count))

    # WTD breach: anyone over 48h in any single week
    wtd_risk = []
    for name, weekly in staff_weekly.items():
        for week, hours in weekly.items():
            if hours > WTD_WEEKLY_LIMIT:
                wtd_risk.append((name, week, hours))

    return shift_coverage, staff_hours, zero_cover, understaffed, wtd_risk


def print_heatmap(shift_cols, shift_coverage, minimum_staff):
    print(f"\n  {'ROTA HEATMAP':^60}")
    print("  " + "─" * 60)

    # Group shifts by week
    weeks = {}
    for shift in shift_cols:
        week = shift.split('_')[0] if '_' in shift else 'Week1'
        weeks.setdefault(week, []).append(shift)

    for week, shifts in sorted(weeks.items()):
        print(f"\n  {Fore.CYAN}{week}{Style.RESET_ALL}")
        for shift in shifts:
            count = len(shift_coverage[shift])
            day_shift = '_'.join(shift.split('_')[1:]) if '_' in shift else shift

            if count == 0:
                colour = Fore.RED
                symbol = '██'
                label = 'ZERO COVER'
            elif count < minimum_staff:
                colour = Fore.YELLOW
                symbol = '▓▓'
                label = f'UNDERSTAFFED ({count}/{minimum_staff})'
            else:
                colour = Fore.GREEN
                symbol = '░░'
                label = f'{count} staff'

            names = ', '.join(shift_coverage[shift]) if shift_coverage[shift] else 'NONE'
            print(f"    {colour}{symbol}{Style.RESET_ALL} {day_shift:<22} {colour}{label:<22}{Style.RESET_ALL} {Fore.WHITE}{names}{Style.RESET_ALL}")


def print_report(shift_coverage, staff_hours, zero_cover, understaffed, wtd_risk, minimum_staff, shift_cols):
    init(autoreset=True)

    print(Fore.CYAN + Style.BRIGHT + "\n  Staff Rota Coverage Analyser")
    print(Fore.CYAN + "  by Phil Atkins — phillipatkins.co.uk")
    print("\n  " + "─" * 60)

    # Zero cover
    if zero_cover:
        print(f"\n  {Fore.RED}{Style.BRIGHT}ZERO COVER SHIFTS ({len(zero_cover)}):{Style.RESET_ALL}")
        for shift in zero_cover:
            print(f"    {Fore.RED}✗ {shift}{Style.RESET_ALL}")
    else:
        print(f"\n  {Fore.GREEN}No zero-cover shifts found.{Style.RESET_ALL}")

    # Understaffed
    if understaffed:
        print(f"\n  {Fore.YELLOW}{Style.BRIGHT}UNDERSTAFFED SHIFTS ({len(understaffed)}):{Style.RESET_ALL}")
        for shift, count in understaffed:
            print(f"    {Fore.YELLOW}⚠ {shift} — {count} staff (minimum: {minimum_staff}){Style.RESET_ALL}")
    else:
        print(f"\n  {Fore.GREEN}All shifts meet minimum staffing.{Style.RESET_ALL}")

    # Staff hours
    print(f"\n  {'STAFF HOURS SUMMARY':}")
    print("  " + "─" * 40)
    for name, hours in sorted(staff_hours.items(), key=lambda x: -x[1]):
        monthly_equivalent_weeks = 4
        avg_weekly = hours / monthly_equivalent_weeks
        if hours > WTD_WEEKLY_LIMIT * 4:
            colour = Fore.RED
            flag = f"  ← OVERTIME RISK (avg {avg_weekly:.0f}h/wk)"
        else:
            colour = Fore.GREEN
            flag = ''
        print(f"  {colour}{name:<16} {hours:>4}h total{flag}{Style.RESET_ALL}")

    # WTD detail
    if wtd_risk:
        print(f"\n  {Fore.RED}{Style.BRIGHT}WORKING TIME DIRECTIVE — AT RISK ({len(set(n for n, _, _ in wtd_risk))} staff):{Style.RESET_ALL}")
        for name, week, hours in sorted(wtd_risk, key=lambda x: -x[2]):
            print(f"    {Fore.RED}{name}: {hours}h in Week {week} (limit: {WTD_WEEKLY_LIMIT}h){Style.RESET_ALL}")

    # Heatmap
    print_heatmap(shift_cols, shift_coverage, minimum_staff)

    # Summary
    print("\n  " + "─" * 60)
    print(f"  {Fore.RED}Zero-cover shifts : {len(zero_cover)}{Style.RESET_ALL}")
    print(f"  {Fore.YELLOW}Understaffed shifts: {len(understaffed)}{Style.RESET_ALL}")
    print(f"  {Fore.RED}Overtime risk      : {len(set(n for n, _, _ in wtd_risk))} staff{Style.RESET_ALL}")
    print()


def export_report(filepath, zero_cover, understaffed, wtd_risk, staff_hours):
    out_path = filepath.replace('.csv', '_report.txt')
    with open(out_path, 'w') as f:
        from datetime import datetime
        f.write(f"Rota Analysis Report — {datetime.now().strftime('%Y-%m-%d %H:%M')}\n\n")
        f.write(f"Zero-cover shifts ({len(zero_cover)}):\n")
        for s in zero_cover:
            f.write(f"  {s}\n")
        f.write(f"\nUnderstaffed shifts ({len(understaffed)}):\n")
        for s, c in understaffed:
            f.write(f"  {s}: {c} staff\n")
        f.write(f"\nOvertime risk ({len(set(n for n, _, _ in wtd_risk))} staff):\n")
        for name, week, hours in wtd_risk:
            f.write(f"  {name}: {hours}h in Week {week}\n")
        f.write("\nStaff hours:\n")
        for name, hours in sorted(staff_hours.items()):
            f.write(f"  {name}: {hours}h\n")
    return out_path


def output_json(shift_coverage, staff_hours, zero_cover, understaffed, wtd_risk, minimum, shift_cols):
    import json
    heatmap = []
    for shift in shift_cols:
        count = len(shift_coverage[shift])
        if count == 0:
            status = 'zero'
        elif count < minimum:
            status = 'under'
        else:
            status = 'ok'
        heatmap.append({'shift': shift, 'count': count, 'status': status, 'staff': shift_coverage[shift]})

    wtd_names = list(set(n for n, _, _ in wtd_risk))
    staff_list = [{'name': n, 'hours': h, 'wtd_risk': n in wtd_names}
                  for n, h in sorted(staff_hours.items(), key=lambda x: -x[1])]

    print(json.dumps({
        'zero_cover': zero_cover,
        'understaffed': [{'shift': s, 'count': c} for s, c in understaffed],
        'wtd_risk': [{'name': n, 'week': w, 'hours': h} for n, w, h in wtd_risk],
        'staff_hours': staff_list,
        'heatmap': heatmap,
        'summary': {
            'zero_cover_count': len(zero_cover),
            'understaffed_count': len(understaffed),
            'wtd_risk_count': len(set(n for n, _, _ in wtd_risk)),
        }
    }))


def main():
    parser = argparse.ArgumentParser(description='Staff rota coverage analyser')
    parser.add_argument('rota', nargs='?', default='sample_rota.csv', help='Path to rota CSV')
    parser.add_argument('--min', type=int, default=2, dest='minimum',
                        help='Minimum staff per shift (default: 2)')
    parser.add_argument('--format', choices=['terminal', 'json'], default='terminal')
    args = parser.parse_args()

    try:
        rows, fieldnames = load_rota(args.rota)
    except FileNotFoundError:
        print(f"File not found: {args.rota}")
        sys.exit(1)

    shift_cols = parse_shifts(fieldnames)
    shift_coverage, staff_hours, zero_cover, understaffed, wtd_risk = analyse(
        rows, shift_cols, args.minimum
    )

    if args.format == 'json':
        output_json(shift_coverage, staff_hours, zero_cover, understaffed, wtd_risk, args.minimum, shift_cols)
        return

    print_report(shift_coverage, staff_hours, zero_cover, understaffed, wtd_risk, args.minimum, shift_cols)

    out_path = export_report(args.rota, zero_cover, understaffed, wtd_risk, staff_hours)
    print(f"  Report saved → {out_path}\n")


if __name__ == '__main__':
    main()
