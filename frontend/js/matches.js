
(function () {
    "use strict";

    const $ = id => document.getElementById(id);

    const tournamentSelect = $("tournamentSelect");
    const matchForm = $("matchForm");
    const team1Select = $("team1");
    const team2Select = $("team2");
    const message = $("message");
    const matchList = $("matchList");
    const generateButton = $("generateButton");

    let tournaments = [];
    let teams = [];
    let matches = [];

    function say(text, error = false) {
        message.textContent = text;
        message.style.color = error ? "#b42318" : "#167544";
    }

    function selectedTournament() {
        return tournamentSelect.value
            ? Number(tournamentSelect.value)
            : null;
    }

    async function loadTournaments() {
        try {
            tournaments = await API.getTournaments();

            tournamentSelect.replaceChildren(
                new Option("Select tournament", "")
            );

            tournaments.forEach(tournament => {
                tournamentSelect.add(
                    new Option(
                        `${tournament.name} (${tournament.sport})`,
                        tournament.id
                    )
                );
            });

            if (tournaments.length > 0) {
                tournamentSelect.value = String(tournaments[0].id);
                await loadTournamentData();
            } else {
                say("Create a tournament first.", true);
            }
        } catch (error) {
            say(error.message, true);
        }
    }

    async function loadTournamentData() {
        const tournamentId = selectedTournament();

        if (!tournamentId) {
            teams = [];
            matches = [];
            populateTeamOptions();
            renderMatches();
            return;
        }

        try {
            const [teamData, matchData] = await Promise.all([
                API.getTeams(tournamentId),
                API.getMatches(tournamentId)
            ]);

            teams = teamData || [];
            matches = matchData || [];

            populateTeamOptions();
            renderMatches();

            const tournament = tournaments.find(
                item => Number(item.id) === tournamentId
            );

            generateButton.disabled = !tournament ||
                !["round_robin", "both"].includes(tournament.format);
        } catch (error) {
            say(error.message, true);
        }
    }

    function populateTeamOptions() {
        [team1Select, team2Select].forEach((select, index) => {
            select.replaceChildren(
                new Option(
                    index === 0 ? "Select first team" : "Select second team",
                    ""
                )
            );

            teams.forEach(team => {
                select.add(new Option(team.name, team.id));
            });
        });
    }

    function renderMatches() {
        matchList.replaceChildren();

        if (matches.length === 0) {
            const row = matchList.insertRow();
            const cell = row.insertCell();
            cell.colSpan = 7;
            cell.textContent = "No matches yet. Generate fixtures or schedule a match.";
            return;
        }

        matches.forEach((match, index) => {
            const row = matchList.insertRow();

            let dateText = "Not scheduled";

            if (match.match_date) {
                const date = new Date(
                    String(match.match_date).replace(" ", "T")
                );

                if (!Number.isNaN(date.getTime())) {
                    dateText = date.toLocaleString();
                }
            }

            [
                index + 1,
                match.team1_name + (
                    match.group_name ? " · " + match.group_name : ""
                ),
                match.team2_name,
                dateText,
                match.venue || "—",
                match.status
            ].forEach(value => {
                row.insertCell().textContent = value;
            });

            const actionCell = row.insertCell();

            if (match.status !== "completed") {
                const button = document.createElement("button");
                button.type = "button";
                button.textContent = match.status === "ongoing"
                    ? "Mark scheduled"
                    : "Mark ongoing";

                button.addEventListener("click", async () => {
                    try {
                        const newStatus = match.status === "ongoing"
                            ? "scheduled"
                            : "ongoing";

                        await API.updateMatchStatus(match.id, newStatus);
                        await loadTournamentData();
                        say("Match status updated.");
                    } catch (error) {
                        say(error.message, true);
                    }
                });

                actionCell.appendChild(button);
            } else {
                actionCell.textContent = "Completed";
            }
        });
    }

    generateButton.addEventListener("click", async () => {
        const tournamentId = selectedTournament();

        if (!tournamentId) {
            say("Select a tournament first.", true);
            return;
        }

        if (!confirm("Generate missing round-robin fixtures?")) {
            return;
        }

        generateButton.disabled = true;

        try {
            const result = await API.generateRoundRobin(tournamentId);
            await loadTournamentData();

            say(
                `Created ${result.created_count} matches. ` +
                `Existing fixtures skipped: ${result.skipped_existing_count}.`
            );
        } catch (error) {
            say(error.message, true);
        } finally {
            generateButton.disabled = false;
        }
    });

    tournamentSelect.addEventListener("change", loadTournamentData);

    matchForm.addEventListener("submit", async event => {
        event.preventDefault();

        const tournamentId = selectedTournament();
        const team1Id = Number(team1Select.value);
        const team2Id = Number(team2Select.value);
        const date = $("matchDate").value;
        const time = $("matchTime").value;
        const venue = $("venue").value.trim();

        if (!tournamentId || !team1Id || !team2Id) {
            say("Select a tournament and both teams.", true);
            return;
        }

        if (team1Id === team2Id) {
            say("A team cannot play against itself.", true);
            return;
        }

        if (!date || !time) {
            say("Select the match date and time.", true);
            return;
        }

        try {
            await API.scheduleMatch({
                tournament_id: tournamentId,
                team1_id: team1Id,
                team2_id: team2Id,
                match_date: `${date}T${time}`,
                venue,
                group_id: ""
            });

            matchForm.reset();
            await loadTournamentData();
            say("Match scheduled successfully.");
        } catch (error) {
            say(error.message, true);
        }
    });

    loadTournaments();
})();

