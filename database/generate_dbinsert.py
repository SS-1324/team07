#!/usr/bin/env python3
# Author: Zhu Junjie (team07 / V3)
# Purpose: Generate dbinsert.sql for the "FA contract efficiency analysis" project
#          following the team's ERD v1 (leagues, teams, players, team_seasons,
#          player_seasons, fa_contracts).
#
# INPUT FOLDERS (create them next to this script)
#   data/lahman/   Lahman CSV files: People.csv, Teams.csv, Batting.csv,
#                  Pitching.csv, Appearances.csv  (subfolders are searched)
#   data/war/      Baseball-Reference WAR files: war_daily_bat.txt,
#                  war_daily_pitch.txt
#   data/manual/   Files the team prepares by hand:
#     fa_contracts.csv   REQUIRED  player_id,from_team_id,to_team_id,start_year,
#                        end_year,total_value[,guaranteed_value,signed_date]
#                        player_id = bbrefID (MLB) or k_xxx (KBO);
#                        team ids = Lahman teamID (MLB) or K_xxx (KBO);
#                        from_team_id may be empty. MLB values in USD,
#                        KBO values in units of 10,000 KRW.
#     name_kor.csv       optional  player_id,name_kor
#     kbo_teams.csv, kbo_team_seasons.csv, kbo_players.csv,
#     kbo_player_seasons.csv   REQUIRED for the full MLB + KBO dataset. Column
#                        names must match the corresponding ERD table columns.
#
# RUN:  python generate_dbinsert.py      ->  dbinsert.sql (requires WAR + KBO files)
#       python generate_dbinsert.py --allow-incomplete -> partial SQL for testing
import argparse
import os
import sys

import numpy as np
import pandas as pd

parser = argparse.ArgumentParser(description='Generate dbinsert.sql from Lahman, WAR, and KBO data.')
parser.add_argument(
    '--allow-incomplete',
    action='store_true',
    help='generate a partial SQL file when WAR or KBO input files are missing',
)
ARGS = parser.parse_args()

# ------------------------------------------------------------------ settings
LAHMAN_DIR = 'data/lahman'
WAR_DIR = 'data/war'
MANUAL_DIR = 'data/manual'
OUT_FILE = 'dbinsert.sql'
DB_NAME = 'team07'
YEAR_FIRST, YEAR_LAST = 2014, 2025   # season range loaded into team_seasons
SEASONS_BEFORE_CONTRACT = 2          # seasons before start_year kept per contract
MIN_PA_AS_BATTER = 50                # fewer plate appearances -> batting stats NULL
EXTRA_PLAYERS = 0                    # extra MLB players without contracts (top WAR)
BATCH = 200                          # rows per INSERT statement
WAR_FILES = ('war_daily_bat.txt', 'war_daily_pitch.txt')
WAR_COLS = {'pid': 'player_ID', 'year': 'year_ID', 'stint': 'stint_ID', 'war': 'WAR'}
NICKNAMES_2WORD = ['Red Sox', 'White Sox', 'Blue Jays']

COLS = {
    'leagues': ['league_id', 'league_name', 'country', 'currency', 'games_per_season'],
    'teams': ['team_id', 'league_id', 'sub_league', 'team_name', 'city'],
    'players': ['player_id', 'name_first', 'name_last', 'name_kor', 'birth_date',
                'birth_country', 'bats', 'throws', 'primary_pos', 'current_team_id'],
    'team_seasons': ['team_id', 'year_id', 'wins', 'losses', 'win_pct', 'league_rank',
                     'postseason', 'runs_scored', 'runs_allowed', 'fa_payroll'],
    'player_seasons': ['player_id', 'year_id', 'stint', 'team_id', 'games', 'war', 'pa',
                       'hr', 'rbi', 'sb', 'avg', 'ops', 'ip', 'p_wins', 'p_so', 'era'],
    'fa_contracts': ['contract_id', 'player_id', 'from_team_id', 'to_team_id',
                     'start_year', 'end_year', 'years', 'total_value',
                     'guaranteed_value', 'aav', 'signed_date'],
}


# ------------------------------------------------------------------ helpers
def read(path, **kw):
    """read_csv that also copes with a UTF-8 BOM (Lahman/Excel files)."""
    return pd.read_csv(path, encoding='utf-8-sig', **kw)

def find(root, name):
    for d, _, files in os.walk(root):
        for f in files:
            if f.lower() == name.lower():
                return os.path.join(d, f)
    return None


def need(root, name):
    p = find(root, name)
    if p is None:
        sys.exit(f'[ERROR] {name} not found under {root}')
    return p


def optional_csv(name, **kw):
    p = find(MANUAL_DIR, name)
    if p is None:
        print(f'[info] {name} not found -> skipped')
        return None
    return read(p, **kw)


def lit(v):
    """Python value -> SQL literal."""
    if v is None or (not isinstance(v, str) and pd.isna(v)):
        return 'NULL'
    if isinstance(v, (bool, np.bool_)):
        return '1' if v else '0'
    if isinstance(v, (int, np.integer)):
        return str(int(v))
    if isinstance(v, (float, np.floating)):
        s = format(float(v), '.4f').rstrip('0').rstrip('.')
        return s if s not in ('', '-0') else '0'
    s = str(v).replace('\\', '\\\\').replace("'", "''")
    return "'" + s + "'"


def emit(f, table, df):
    cols = COLS[table]
    df = df[cols].astype(object)
    f.write(f'-- {table}: {len(df)} rows\n')
    buf = []

    def flush():
        if buf:
            f.write(f'INSERT INTO {table} ({", ".join(cols)}) VALUES\n')
            f.write(',\n'.join(buf) + ';\n')
            buf.clear()

    for row in df.itertuples(index=False, name=None):
        buf.append('(' + ', '.join(lit(v) for v in row) + ')')
        if len(buf) >= BATCH:
            flush()
    flush()
    f.write('\n')


CITY_FIX = {'Los Angeles Angels of Anaheim': 'Los Angeles', 'Athletics': 'West Sacramento',
            'Oakland Athletics': 'Oakland'}


def split_city(name):
    if name in CITY_FIX:
        return CITY_FIX[name]
    for n in NICKNAMES_2WORD:
        if name.endswith(n):
            return name[:-len(n)].strip()
    return name.rsplit(' ', 1)[0] if ' ' in name else name


def div(a, b):
    return a / b.where(b > 0)


# ------------------------------------------------------------------ load
print('loading Lahman ...')
people = read(need(LAHMAN_DIR, 'People.csv'))
people['pid'] = people['bbrefID'].fillna(people['playerID'])
l2p = dict(zip(people['playerID'], people['pid']))
people = people.drop_duplicates('pid').set_index('pid')

lt = read(need(LAHMAN_DIR, 'Teams.csv'))
lt = lt[(lt.yearID >= YEAR_FIRST) & (lt.yearID <= YEAR_LAST) & lt.lgID.isin(['AL', 'NL'])]

contracts = read(need(MANUAL_DIR, 'fa_contracts.csv'),
                        dtype={'player_id': str, 'from_team_id': str, 'to_team_id': str,
                               'signed_date': str})
for c in ('start_year', 'end_year', 'total_value'):
    if c not in contracts:
        sys.exit(f'[ERROR] fa_contracts.csv needs column {c}')
if 'guaranteed_value' not in contracts:
    contracts['guaranteed_value'] = np.nan
if 'signed_date' not in contracts:
    contracts['signed_date'] = np.nan
contracts['years'] = contracts.end_year - contracts.start_year + 1
contracts['guaranteed_value'] = contracts.guaranteed_value.fillna(contracts.total_value)
contracts['aav'] = (contracts.total_value / contracts.years).round()
for c in ('start_year', 'end_year', 'years', 'total_value', 'guaranteed_value', 'aav'):
    contracts[c] = contracts[c].astype('Int64')
contracts.insert(0, 'contract_id', range(1, len(contracts) + 1))

kbo_teams = optional_csv('kbo_teams.csv')
kbo_ts = optional_csv('kbo_team_seasons.csv')
kbo_players = optional_csv('kbo_players.csv')
kbo_ps = optional_csv('kbo_player_seasons.csv')
name_kor = optional_csv('name_kor.csv')
kbo_ids = set(kbo_players['player_id']) if kbo_players is not None else set()

missing_inputs = [
    os.path.join(WAR_DIR, name) for name in WAR_FILES
    if find(WAR_DIR, name) is None
]
missing_inputs.extend(
    os.path.join(MANUAL_DIR, name)
    for name, data in (
        ('kbo_teams.csv', kbo_teams),
        ('kbo_team_seasons.csv', kbo_ts),
        ('kbo_players.csv', kbo_players),
        ('kbo_player_seasons.csv', kbo_ps),
    )
    if data is None
)
if missing_inputs and not ARGS.allow_incomplete:
    sys.exit(
        '[ERROR] Full MLB + KBO data requires these input files:\n  - '
        + '\n  - '.join(missing_inputs)
        + '\nUse --allow-incomplete only for a partial test export.'
    )
if missing_inputs:
    print('[warn] generating partial data; missing required inputs:')
    for path in missing_inputs:
        print(f'  - {path}')

# ------------------------------------------------------------------ leagues
leagues = pd.DataFrame([
    ['MLB', 'Major League Baseball', 'USA', 'USD', 162],
    ['KBO', 'KBO League', 'South Korea', 'KRW', 144]], columns=COLS['leagues'])

# ------------------------------------------------------------------ teams (MLB)
latest = lt.sort_values('yearID').drop_duplicates('teamID', keep='last')
teams = pd.DataFrame({
    'team_id': latest.teamID, 'league_id': 'MLB', 'sub_league': latest.lgID,
    'team_name': latest['name'], 'city': latest['name'].map(split_city)})
if kbo_teams is not None:
    kt = kbo_teams.copy()
    kt['league_id'] = 'KBO'
    kt['sub_league'] = None
    teams = pd.concat([teams, kt[COLS['teams']]], ignore_index=True)
team_ids = set(teams.team_id)

# ------------------------------------------------------------------ team_seasons (MLB)
lt = lt.copy()
lt['win_pct'] = (lt.W / (lt.W + lt.L)).round(3)
lt['league_rank'] = lt.groupby(['yearID', 'lgID'])['W'].rank(ascending=False,
                                                              method='min').astype(int)


def post(r):
    if r.WSWin == 'Y':
        return 'WS'
    if r.LgWin == 'Y':
        return 'CS'
    if r.DivWin == 'Y':
        return 'DS'
    if r.WCWin == 'Y':
        return 'WC'
    return 'NONE'


lt['postseason'] = lt.apply(post, axis=1)
ts = pd.DataFrame({
    'team_id': lt.teamID, 'year_id': lt.yearID, 'wins': lt.W, 'losses': lt.L,
    'win_pct': lt.win_pct, 'league_rank': lt.league_rank, 'postseason': lt.postseason,
    'runs_scored': lt.R, 'runs_allowed': lt.RA, 'fa_payroll': 0})
if kbo_ts is not None:
    k = kbo_ts.copy()
    k['fa_payroll'] = 0
    ts = pd.concat([ts, k[COLS['team_seasons']]], ignore_index=True)
ts_keys = set(zip(ts.team_id, ts.year_id))

# ------------------------------------------------------------------ contracts checks + fa_payroll
bad = contracts[~contracts.to_team_id.isin(team_ids) |
                (contracts.from_team_id.notna() & ~contracts.from_team_id.isin(team_ids))]
if len(bad):
    print('[ERROR] unknown team id in fa_contracts.csv:')
    print(bad[['contract_id', 'player_id', 'from_team_id', 'to_team_id']].to_string())
    sys.exit(1)
payroll = {}
for r in contracts.itertuples():
    for y in range(int(r.start_year), int(r.end_year) + 1):
        if (r.to_team_id, y) in ts_keys:
            payroll[(r.to_team_id, y)] = payroll.get((r.to_team_id, y), 0) + int(r.aav)
ts['fa_payroll'] = [payroll.get(k, 0) for k in zip(ts.team_id, ts.year_id)]

# ------------------------------------------------------------------ which MLB players / seasons
mlb_contract_pids = [p for p in contracts.player_id.unique() if p not in kbo_ids]
missing = [p for p in mlb_contract_pids if p not in people.index]
if missing:
    print('[ERROR] player_id not found in People.bbrefID:', missing)
    sys.exit(1)

want = {}
for r in contracts.itertuples():
    if r.player_id in kbo_ids:
        continue
    ys = range(max(YEAR_FIRST, int(r.start_year) - SEASONS_BEFORE_CONTRACT),
               min(YEAR_LAST, int(r.end_year)) + 1)
    want.setdefault(r.player_id, set()).update(ys)

# WAR (bat + pitch summed per player-year-stint)
war_parts = []
for fn in WAR_FILES:
    p = find(WAR_DIR, fn)
    if p is None:
        print(f'[warn] {fn} not found -> war will be NULL for its part')
        continue
    d = read(p, usecols=list(WAR_COLS.values()), na_values=['NULL'])
    d.columns = list(WAR_COLS.keys())
    d['war'] = pd.to_numeric(d['war'], errors='coerce')
    war_parts.append(d)
if war_parts:
    war = pd.concat(war_parts).groupby(['pid', 'year', 'stint'], as_index=False)['war'].sum(min_count=1)
else:
    war = pd.DataFrame({'pid': pd.Series(dtype='object'), 'year': pd.Series(dtype='int64'),
                        'stint': pd.Series(dtype='int64'), 'war': pd.Series(dtype='float64')})

if EXTRA_PLAYERS > 0 and len(war):
    w = war[(war.year >= YEAR_FIRST) & (war.year <= YEAR_LAST) & war.pid.isin(people.index)]
    top = w.groupby('pid')['war'].sum().sort_values(ascending=False)
    top = [p for p in top.index if p not in want][:EXTRA_PLAYERS]
    for p in top:
        want[p] = set(range(YEAR_FIRST, YEAR_LAST + 1))

want_df = pd.DataFrame([(p, y) for p, ys in want.items() for y in ys], columns=['pid', 'yearID'])

# ------------------------------------------------------------------ player_seasons (MLB)
print('building player_seasons ...')
bat = read(need(LAHMAN_DIR, 'Batting.csv'))
bat = bat[(bat.yearID >= YEAR_FIRST) & (bat.yearID <= YEAR_LAST)].copy()
bat['pid'] = bat.playerID.map(l2p)
bat = bat.merge(want_df, on=['pid', 'yearID'])
for c in ['AB', 'H', '2B', '3B', 'HR', 'RBI', 'SB', 'BB', 'HBP', 'SF', 'SH']:
    bat[c] = bat[c].fillna(0)
bat['pa'] = bat.AB + bat.BB + bat.HBP + bat.SF + bat.SH
bat['avg'] = div(bat.H, bat.AB)
tb = bat.H + bat['2B'] + 2 * bat['3B'] + 3 * bat.HR
bat['ops'] = div(bat.H + bat.BB + bat.HBP, bat.AB + bat.BB + bat.HBP + bat.SF) + div(tb, bat.AB)
bat['avg'] = bat['avg'].round(3)      # DECIMAL(4,3)
bat['ops'] = bat['ops'].round(3)
bat = bat.rename(columns={'G': 'b_games', 'teamID': 'b_team', 'HR': 'hr', 'RBI': 'rbi', 'SB': 'sb'})
bat = bat[['pid', 'yearID', 'stint', 'b_team', 'b_games', 'pa', 'hr', 'rbi', 'sb', 'avg', 'ops']]

pit = read(need(LAHMAN_DIR, 'Pitching.csv'))
pit = pit[(pit.yearID >= YEAR_FIRST) & (pit.yearID <= YEAR_LAST)].copy()
pit['pid'] = pit.playerID.map(l2p)
pit_all = pit.copy()                       # kept for primary_pos
pit = pit.merge(want_df, on=['pid', 'yearID'])
pit['ip'] = (pit.IPouts / 3).round(1)
pit['era'] = (pit.ER * 27 / pit.IPouts.where(pit.IPouts > 0)).round(2)
pit = pit.rename(columns={'G': 'p_games', 'teamID': 'p_team', 'W': 'p_wins', 'SO': 'p_so'})
pit = pit[['pid', 'yearID', 'stint', 'p_team', 'p_games', 'ip', 'p_wins', 'p_so', 'era']]

m = bat.merge(pit, on=['pid', 'yearID', 'stint'], how='outer')
has_b, has_p = m.pa.notna(), m.ip.notna()
use_b = has_b & ((m.pa >= MIN_PA_AS_BATTER) | ~has_p)
for c in ['pa', 'hr', 'rbi', 'sb', 'avg', 'ops']:
    m[c] = m[c].where(use_b)
m['games'] = pd.concat([m.b_games.where(use_b), m.p_games.where(has_p)], axis=1).max(axis=1)
m['team_id'] = m.b_team.fillna(m.p_team)
m = m.merge(war.rename(columns={'year': 'yearID'}), on=['pid', 'yearID', 'stint'], how='left')
m['war'] = pd.to_numeric(m['war'], errors='coerce').round(1)   # DECIMAL(4,1)
ps = m.rename(columns={'pid': 'player_id', 'yearID': 'year_id'})
n0 = len(ps)
ps = ps[[(t, y) in ts_keys for t, y in zip(ps.team_id, ps.year_id)]]
if len(ps) < n0:
    print(f'[warn] dropped {n0 - len(ps)} player_seasons rows without a matching team_seasons row')
ps = ps[COLS['player_seasons']]
if kbo_ps is not None:
    kbo_ps = kbo_ps.copy()
    # Non-numeric KBO values such as '-' (e.g. ERA of a 0-inning pitcher) become NULL.
    # Without this the whole column is written as quoted strings and '-' breaks strict-mode import.
    for c in COLS['player_seasons']:
        if c in ('player_id', 'team_id'):
            continue
        raw = kbo_ps[c]
        kbo_ps[c] = pd.to_numeric(raw, errors='coerce')
        bad = raw.notna() & kbo_ps[c].isna()
        if bad.any():
            print(f'[warn] kbo_player_seasons.{c}: {int(bad.sum())} non-numeric value(s) -> NULL '
                  f'{sorted(set(raw[bad].astype(str)))[:5]}')
    ps = pd.concat([ps, kbo_ps[COLS['player_seasons']]], ignore_index=True)
ps = ps.sort_values(['player_id', 'year_id', 'stint'])
assert not ps.duplicated(['player_id', 'year_id', 'stint']).any(), 'duplicate player_seasons PK'
print(f'  war missing in {int(ps.war.isna().sum())} of {len(ps)} rows')

# ------------------------------------------------------------------ players
print('building players ...')
app = read(need(LAHMAN_DIR, 'Appearances.csv'))
app = app[(app.yearID >= YEAR_FIRST) & (app.yearID <= YEAR_LAST)].copy()
app['pid'] = app.playerID.map(l2p)
POS = {'G_c': 'C', 'G_1b': '1B', 'G_2b': '2B', 'G_3b': '3B', 'G_ss': 'SS',
       'G_lf': 'LF', 'G_cf': 'CF', 'G_rf': 'RF', 'G_dh': 'DH'}
fieldg = app.groupby('pid')[list(POS)].sum()
pg = pit_all.groupby('pid')[['G', 'GS']].sum()


def primary(pid):
    f = fieldg.loc[pid] if pid in fieldg.index else None
    best = f.idxmax() if f is not None and f.max() > 0 else None
    fg = f.max() if best else 0
    if pid in pg.index and pg.loc[pid, 'G'] >= max(fg, 1):
        return 'SP' if pg.loc[pid, 'GS'] / pg.loc[pid, 'G'] >= 0.5 else 'RP'
    return POS[best] if best else None


last_team = ps.sort_values(['year_id', 'stint']).drop_duplicates('player_id', keep='last') \
    .set_index('player_id')['team_id'].to_dict()
rows = []
for pid in sorted(want):
    p = people.loc[pid]
    bd = None
    if pd.notna(p.birthYear) and pd.notna(p.birthMonth) and pd.notna(p.birthDay):
        bd = f'{int(p.birthYear):04d}-{int(p.birthMonth):02d}-{int(p.birthDay):02d}'
    cur = last_team.get(pid)
    rows.append([pid, p.nameFirst, p.nameLast, None, bd, p.birthCountry, p.bats, p.throws,
                 primary(pid), cur if cur in team_ids else None])
players = pd.DataFrame(rows, columns=COLS['players'])
if kbo_players is not None:
    kp = kbo_players.copy()
    for c in COLS['players']:
        if c not in kp:
            kp[c] = None
    players = pd.concat([players, kp[COLS['players']]], ignore_index=True)
if name_kor is not None:
    mk = dict(zip(name_kor.player_id, name_kor.name_kor))
    players['name_kor'] = [mk.get(p, k) for p, k in zip(players.player_id, players.name_kor)]
players = players.drop_duplicates('player_id')

# ------------------------------------------------------------------ integrity checks
pset = set(players.player_id)
assert set(contracts.player_id) <= pset, 'contract player missing in players'
assert set(ps.player_id) <= pset, 'player_seasons player missing in players'
assert set(zip(ps.team_id, ps.year_id)) <= ts_keys, 'player_seasons -> team_seasons FK broken'
assert set(teams.league_id) <= set(leagues.league_id), 'teams -> leagues FK broken'

# ------------------------------------------------------------------ write
with open(OUT_FILE, 'w', encoding='utf-8') as f:
    f.write('-- dbinsert.sql (generated by generate_dbinsert.py, Author: Zhu Junjie)\n')
    f.write('-- Insert order follows FK dependencies: '
            'leagues > teams > players > team_seasons > player_seasons > fa_contracts\n')
    f.write(f'USE {DB_NAME};\nSET NAMES utf8mb4;\n\n')
    emit(f, 'leagues', leagues)
    emit(f, 'teams', teams)
    emit(f, 'players', players)
    emit(f, 'team_seasons', ts)
    emit(f, 'player_seasons', ps)
    emit(f, 'fa_contracts', contracts)

total = len(leagues) + len(teams) + len(players) + len(ts) + len(ps) + len(contracts)
print(f'done -> {OUT_FILE}')
for name, d in [('leagues', leagues), ('teams', teams), ('players', players),
                ('team_seasons', ts), ('player_seasons', ps), ('fa_contracts', contracts)]:
    print(f'  {name:15s}{len(d):6d}')
print(f'  {"total":15s}{total:6d}')
