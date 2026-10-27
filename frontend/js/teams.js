
const form = document.getElementById("teamForm");
const list = document.getElementById("teamList");
const message = document.getElementById("message");

let teams = [];

// Load previously saved teams
try {
    teams = JSON.parse(
        localStorage.getItem("sportsHubTeams") || "[]"
    );

    if (!Array.isArray(teams)) {
        teams = [];
    }
} catch (error) {
    teams = [];
}

// Display registered teams
function renderTeams() {
    list.replaceChildren();

    if (teams.length === 0) {
        const row = list.insertRow();
        const cell = row.insertCell();

        cell.colSpan = 5;
        cell.textContent = "No teams registered yet.";

        return;
    }

    teams.forEach((team, index) => {
        const row = list.insertRow();

        [index + 1, team.name, team.captain, team.sport]
            .forEach(value => {
                row.insertCell().textContent = value;
            });

        const actionCell = row.insertCell();
        const deleteButton = document.createElement("button");

        deleteButton.textContent = "Delete";

        // Delete a registered team
        deleteButton.addEventListener("click", () => {
            teams.splice(index, 1);

            saveTeams();
            renderTeams();

            message.textContent = "Team deleted.";
        });

        actionCell.appendChild(deleteButton);
    });
}

// Save teams in browser localStorage
function saveTeams() {
    try {
        localStorage.setItem(
            "sportsHubTeams",
            JSON.stringify(teams)
        );
    } catch (error) {
        message.textContent = "Browser storage is unavailable.";
    }
}

// Register a new team
form.addEventListener("submit", event => {
    event.preventDefault();

    const name = document.getElementById("teamName").value.trim();
    const captain = document.getElementById("captainName").value.trim();
    const sport = document.getElementById("sport").value;

    // Check that all fields are completed
    if (!name || !captain || !sport) {
        message.textContent = "Please complete all fields.";
        return;
    }

    // Prevent duplicate team names
    if (
        teams.some(
            team => team.name.toLowerCase() === name.toLowerCase()
        )
    ) {
        message.textContent = "That team name is already registered.";
        return;
    }

    // Add the new team
    teams.push({
        name,
        captain,
        sport
    });

    saveTeams();
    renderTeams();

    form.reset();

    message.textContent = "Team registered successfully!";
});

// Display teams when the page opens
renderTeams();
