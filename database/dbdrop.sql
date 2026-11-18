-- dbdrop.sql
-- Team07 (V3) - drops all tables (reverse order of dbinsert.sql because of foreign keys)
-- Author: Zhu Junjie
-- Example: source dbdrop.sql

USE team07;

DROP TABLE IF EXISTS fa_contracts;
DROP TABLE IF EXISTS player_seasons;
DROP TABLE IF EXISTS team_seasons;
DROP TABLE IF EXISTS players;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS leagues;
