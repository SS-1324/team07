-- dbcreate.sql
-- Team07 (V3) - FA contract efficiency analysis system
-- Author: Zhu Junjie (script), table design: ERD v1 by Shin Suyeon
-- Run order: dbdrop.sql -> dbcreate.sql -> dbinsert.sql
-- Example: source dbcreate.sql

USE team07;
SET NAMES utf8mb4;

-- ---------------------------------------------------------------- leagues
CREATE TABLE leagues (
    league_id        CHAR(3)      NOT NULL,
    league_name      VARCHAR(50)  NOT NULL,
    country          VARCHAR(30),
    currency         CHAR(3)      NOT NULL,      -- USD in dollars, KRW in units of 10,000 won
    games_per_season SMALLINT,
    PRIMARY KEY (league_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- teams
CREATE TABLE teams (
    team_id    VARCHAR(5)   NOT NULL,            -- MLB: Lahman teamID, KBO: K_LG style
    league_id  CHAR(3)      NOT NULL,
    sub_league CHAR(2),                          -- AL / NL, NULL for KBO
    team_name  VARCHAR(50)  NOT NULL,
    city       VARCHAR(30),
    PRIMARY KEY (team_id),
    KEY idx_teams_league (league_id),
    CONSTRAINT fk_teams_league FOREIGN KEY (league_id)
        REFERENCES leagues (league_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- players
CREATE TABLE players (
    player_id       VARCHAR(12) NOT NULL,        -- MLB: bbrefID, KBO: k_ prefix code
    name_first      VARCHAR(40),
    name_last       VARCHAR(40),
    name_kor        VARCHAR(20),
    birth_date      DATE,
    birth_country   VARCHAR(30),
    bats            CHAR(1),                     -- R / L / B
    throws          CHAR(1),
    primary_pos     VARCHAR(3),                  -- SP, RP, C, SS ...
    current_team_id VARCHAR(5),                  -- NULL allowed
    PRIMARY KEY (player_id),
    KEY idx_players_name_last (name_last),
    KEY idx_players_current_team (current_team_id),
    CONSTRAINT fk_players_team FOREIGN KEY (current_team_id)
        REFERENCES teams (team_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- team_seasons
CREATE TABLE team_seasons (
    team_id      VARCHAR(5)    NOT NULL,
    year_id      SMALLINT      NOT NULL,
    wins         SMALLINT,
    losses       SMALLINT,
    win_pct      DECIMAL(4,3),
    league_rank  TINYINT,                        -- rank inside AL/NL (MLB) by wins
    postseason   VARCHAR(10),                    -- NONE / WC / DS / CS / WS / KS ...
    runs_scored  SMALLINT,
    runs_allowed SMALLINT,
    fa_payroll   BIGINT        NOT NULL DEFAULT 0,  -- sum of AAV of FA contracts valid that season
    PRIMARY KEY (team_id, year_id),
    KEY idx_ts_year (year_id),
    CONSTRAINT fk_ts_team FOREIGN KEY (team_id)
        REFERENCES teams (team_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- player_seasons
CREATE TABLE player_seasons (
    player_id VARCHAR(12)  NOT NULL,
    year_id   SMALLINT     NOT NULL,
    stint     TINYINT      NOT NULL,             -- 1, 2 ... when traded during a season
    team_id   VARCHAR(5)   NOT NULL,
    games     SMALLINT,
    war       DECIMAL(4,1),                      -- batting + pitching WAR
    pa        SMALLINT,                          -- batter stats (NULL for pitchers)
    hr        SMALLINT,
    rbi       SMALLINT,
    sb        SMALLINT,
    `avg`     DECIMAL(4,3),
    ops       DECIMAL(4,3),
    ip        DECIMAL(5,1),                      -- pitcher stats (NULL for batters)
    p_wins    SMALLINT,
    p_so      SMALLINT,
    era       DECIMAL(5,2),
    PRIMARY KEY (player_id, year_id, stint),
    KEY idx_ps_year (year_id),
    KEY idx_ps_team_year (team_id, year_id),
    CONSTRAINT fk_ps_player FOREIGN KEY (player_id)
        REFERENCES players (player_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_ps_team_season FOREIGN KEY (team_id, year_id)
        REFERENCES team_seasons (team_id, year_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------- fa_contracts
CREATE TABLE fa_contracts (
    contract_id      INT         NOT NULL AUTO_INCREMENT,
    player_id        VARCHAR(12) NOT NULL,
    from_team_id     VARCHAR(5),                 -- previous team, NULL allowed
    to_team_id       VARCHAR(5)  NOT NULL,
    start_year       SMALLINT    NOT NULL,
    end_year         SMALLINT    NOT NULL,
    years            TINYINT     NOT NULL,
    total_value      BIGINT      NOT NULL,
    guaranteed_value BIGINT,
    aav              BIGINT      NOT NULL,       -- total_value / years
    signed_date      DATE,
    PRIMARY KEY (contract_id),
    KEY idx_fc_to_start (to_team_id, start_year),
    KEY idx_fc_start (start_year),
    KEY idx_fc_player (player_id),
    KEY idx_fc_from (from_team_id),
    CONSTRAINT chk_fc_years CHECK (end_year >= start_year),
    CONSTRAINT fk_fc_player FOREIGN KEY (player_id)
        REFERENCES players (player_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_fc_from FOREIGN KEY (from_team_id)
        REFERENCES teams (team_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_fc_to FOREIGN KEY (to_team_id)
        REFERENCES teams (team_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
