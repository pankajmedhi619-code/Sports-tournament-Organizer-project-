CREATE DATABASE IF NOT EXISTS tournament_db;
USE tournament_db;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS results;
DROP TABLE IF EXISTS matches;
DROP TABLE IF EXISTS group_members;
DROP TABLE IF EXISTS groups;
DROP TABLE IF EXISTS tournament_teams;
DROP TABLE IF EXISTS teams;
DROP TABLE IF EXISTS tournaments;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    sport VARCHAR(50) NOT NULL,
    format ENUM('knockout', 'round_robin', 'both') NOT NULL DEFAULT 'both',
    status ENUM('upcoming', 'ongoing', 'completed') NOT NULL DEFAULT 'upcoming',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    sport VARCHAR(50) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tournament_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    team_id INT NOT NULL,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tournament_team (tournament_id, team_id)
);

CREATE TABLE groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    group_name VARCHAR(50) NOT NULL,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_per_tournament (tournament_id, group_name)
);

CREATE TABLE group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    group_id INT NOT NULL,
    team_id INT NOT NULL,
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (team_id) REFERENCES teams(id) ON DELETE CASCADE,
    FOREIGN KEY (tournament_id, team_id) REFERENCES tournament_teams(tournament_id, team_id) ON DELETE CASCADE,
    UNIQUE KEY unique_team_in_tournament_groups (tournament_id, team_id)
);

CREATE TABLE matches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    group_id INT DEFAULT NULL,
    team1_id INT NOT NULL,
    team2_id INT NOT NULL,
    match_date DATETIME DEFAULT NULL,
    venue VARCHAR(100) DEFAULT NULL,
    stage ENUM('group', 'quarterfinal', 'semifinal', 'final') NOT NULL DEFAULT 'group',
    status ENUM('scheduled', 'ongoing', 'completed') NOT NULL DEFAULT 'scheduled',
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE SET NULL,
    FOREIGN KEY (tournament_id, team1_id) REFERENCES tournament_teams(tournament_id, team_id) ON DELETE CASCADE,
    FOREIGN KEY (tournament_id, team2_id) REFERENCES tournament_teams(tournament_id, team_id) ON DELETE CASCADE,
    CONSTRAINT chk_different_teams CHECK (team1_id <> team2_id)
);

CREATE TABLE results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    match_id INT NOT NULL UNIQUE,
    team1_score INT NOT NULL DEFAULT 0,
    team2_score INT NOT NULL DEFAULT 0,
    winner_team_id INT DEFAULT NULL,
    outcome ENUM('team1_win', 'team2_win', 'draw') NOT NULL,
    recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
    FOREIGN KEY (winner_team_id) REFERENCES teams(id) ON DELETE SET NULL,
    CONSTRAINT chk_scores_non_negative CHECK (team1_score >= 0 AND team2_score >= 0)
);

CREATE INDEX idx_tournament_status ON tournaments(status);
CREATE INDEX idx_match_tournament ON matches(tournament_id);
CREATE INDEX idx_match_status ON matches(status);
CREATE INDEX idx_result_match ON results(match_id);

INSERT INTO tournaments (name, sport, format, status) VALUES
('Inter-College Cricket Cup 2024', 'Cricket', 'both', 'upcoming'),
('Football Premier League 2024', 'Football', 'round_robin', 'ongoing'),
('Basketball Knockout 2024', 'Basketball', 'knockout', 'upcoming');

INSERT INTO teams (name, sport) VALUES
('Team Alpha', 'Cricket'),
('Team Bravo', 'Cricket'),
('Team Charlie', 'Cricket'),
('Team Delta', 'Cricket'),
('Team Echo', 'Cricket'),
('Team Foxtrot', 'Cricket'),
('Red Lions', 'Football'),
('Blue Tigers', 'Football'),
('Green Hawks', 'Football'),
('Black Eagles', 'Football');

INSERT INTO tournament_teams (tournament_id, team_id) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 5), (1, 6),
(2, 7), (2, 8), (2, 9), (2, 10);

INSERT INTO groups (tournament_id, group_name) VALUES
(1, 'Group A'),
(1, 'Group B');

INSERT INTO group_members (tournament_id, group_id, team_id) VALUES
(1, 1, 1), (1, 1, 2), (1, 1, 3),
(1, 2, 4), (1, 2, 5), (1, 2, 6);

INSERT INTO matches (tournament_id, group_id, team1_id, team2_id, match_date, venue, stage, status) VALUES
(1, 1, 1, 2, '2024-12-01 10:00:00', 'Ground A', 'group', 'completed'),
(1, 1, 1, 3, '2024-12-02 10:00:00', 'Ground A', 'group', 'scheduled'),
(1, 1, 2, 3, '2024-12-03 10:00:00', 'Ground B', 'group', 'scheduled'),
(1, 2, 4, 5, '2024-12-01 14:00:00', 'Ground B', 'group', 'completed'),
(1, 2, 4, 6, '2024-12-02 14:00:00', 'Ground A', 'group', 'scheduled'),
(1, 2, 5, 6, '2024-12-03 14:00:00', 'Ground B', 'group', 'scheduled');

INSERT INTO results (match_id, team1_score, team2_score, winner_team_id, outcome) VALUES
(1, 5, 3, 1, 'team1_win'),
(4, 2, 2, NULL, 'draw');
