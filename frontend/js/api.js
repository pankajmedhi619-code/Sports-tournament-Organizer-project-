
(function (root) {
    // All HTML pages are inside the frontend folder.
    const API_BASE = "../backend/php";

    async function request(endpoint, options = {}) {
        const headers = {
            Accept: "application/json",
            ...options.headers
        };

        if (options.body !== undefined) {
            headers["Content-Type"] = "application/json";
        }

        let response;

        try {
            response = await fetch(`${API_BASE}/${endpoint}`, {
                ...options,
                headers
            });
        } catch (error) {
            throw new Error(
                "Cannot connect to the backend. Check that your PHP server is running."
            );
        }

        const responseText = await response.text();
        let payload;

        try {
            payload = JSON.parse(responseText);
        } catch {
            throw new Error(
                `The backend returned invalid JSON (HTTP ${response.status}). Check the PHP endpoint and server configuration.`
            );
        }

        if (!response.ok || payload.status !== "success") {
            throw new Error(
                payload.message || `Request failed: HTTP ${response.status}`
            );
        }

        // PHP returns application data inside payload.data.
        return payload.data ?? null;
    }

    const API = {
        // ------------------------------
        // TEAMS
        // ------------------------------

        getTeams(tournamentId = null) {
            const query = tournamentId !== null
                ? `?tournament_id=${encodeURIComponent(tournamentId)}`
                : "";

            return request(`teams.php${query}`);
        },

        getTeam(teamId) {
            return request(
                `teams.php?id=${encodeURIComponent(teamId)}`
            );
        },

        createTeam(team) {
            return request("teams.php", {
                method: "POST",
                body: JSON.stringify({
                    name: team.name,
                    sport: team.sport,
                    ...(team.tournament_id != null
                        ? { tournament_id: team.tournament_id }
                        : {})
                })
            });
        },

        deleteTeam(teamId) {
            return request(
                `teams.php?id=${encodeURIComponent(teamId)}`,
                { method: "DELETE" }
            );
        },

        // ------------------------------
        // GROUPS
        // ------------------------------

        getGroups(tournamentId) {
            if (tournamentId === null ||
                tournamentId === undefined ||
                tournamentId === "") {
                throw new Error("Tournament ID is required");
            }

            return request(
                `groups.php?tournament_id=${encodeURIComponent(tournamentId)}`
            );
        },

        saveGroups(tournamentId, groups, replace = true) {
            if (tournamentId === null ||
                tournamentId === undefined ||
                tournamentId === "") {
                throw new Error("Tournament ID is required");
            }

            if (!Array.isArray(groups)) {
                throw new TypeError("Groups must be an array");
            }

            // Match the request format expected by groups.php
            const normalizedGroups = groups.map((group, index) => {
                const teamIds = group.teamIds ?? group.team_ids ?? [];

                if (!Array.isArray(teamIds)) {
                    throw new TypeError(
                        `Group ${index + 1} must contain an array of team IDs`
                    );
                }

                return {
                    name: group.name ?? group.group_name ?? `Group ${index + 1}`,
                    team_ids: teamIds
                };
            });

            return request("groups.php", {
                method: "POST",
                body: JSON.stringify({
                    tournament_id: tournamentId,
                    replace: replace,
                    groups: normalizedGroups
                })
            });
        },


        // ------------------------------
        // TOURNAMENTS
        // ------------------------------

        getTournaments(status = null) {
            const query = status
                ? `?status=${encodeURIComponent(status)}`
                : "";

            return request(`tournaments.php${query}`);
        },

        getTournament(tournamentId) {
            return request(
                `tournaments.php?id=${encodeURIComponent(tournamentId)}`
            );
        },

        createTournament(tournament) {
            return request("tournaments.php", {
                method: "POST",
                body: JSON.stringify(tournament)
            });
        },

        updateTournament(tournamentId, updates) {
            return request(
                `tournaments.php?id=${encodeURIComponent(tournamentId)}`,
                {
                    method: "PUT",
                    body: JSON.stringify(updates)
                }
            );
        },

        deleteTournament(tournamentId) {
            return request(
                `tournaments.php?id=${encodeURIComponent(tournamentId)}`,
                { method: "DELETE" }
            );
        },

        addTeamToTournament(tournamentId, teamId) {
            return request("tournaments.php", {
                method: "POST",
                body: JSON.stringify({
                    action: "add_team",
                    tournament_id: tournamentId,
                    team_id: teamId
                })
            });
        }
    };

    // Make API available to other browser scripts and Node.js tests.
    if (typeof module !== "undefined" && module.exports) {
        module.exports = API;
    }

    root.API = API;
})(typeof globalThis !== "undefined" ? globalThis : this);
