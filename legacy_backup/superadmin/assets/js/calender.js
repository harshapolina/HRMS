let events = [],
	eventDateInput = document.getElementById("eventDate"),
	eventTitleInput = document.getElementById("eventTitle"),
	eventDescriptionInput = document.getElementById("eventDescription"),
	reminderList = document.getElementById("reminderList"),
	eventIdCounter = 1;

function generate_year_range(start, end) {
	let options = "";
	for (let year = start; year <= end; year++) {
		options += "<option value='" + year + "'>" + year + "</option>";
	}
	return options;
}

const today = new Date();
let currentMonth = today.getMonth();
let currentYear = today.getFullYear();
const selectYear = document.getElementById("year");
const selectMonth = document.getElementById("month");
const createYear = generate_year_range(1970, 2050);
document.getElementById("year").innerHTML = createYear;

const calendar = document.getElementById("calendar");
const months = [
	"January",
	"February",
	"March",
	"April",
	"May",
	"June",
	"July",
	"August",
	"September",
	"October",
	"November",
	"December",
];
const days = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

let $dataHead = "<tr>";
for (dhead in days) {
	$dataHead += "<th data-days='" + days[dhead] + "'>" + days[dhead] + "</th>";
}

function next() {
	currentYear = currentMonth === 11 ? currentYear + 1 : currentYear;
	currentMonth = (currentMonth + 1) % 12;
	showCalendar(currentMonth, currentYear);
}

function previous() {
	currentYear = currentMonth === 0 ? currentYear - 1 : currentYear;
	currentMonth = currentMonth === 0 ? 11 : currentMonth - 1;
	showCalendar(currentMonth, currentYear);
}

function jump() {
	currentYear = parseInt(selectYear.value, 10);
	currentMonth = parseInt(selectMonth.value, 10);
	showCalendar(currentMonth, currentYear);
}

function showCalendar(month, year) {
	const firstDay = new Date(year, month).getDay();
	const tbl = document.getElementById("calendar-body");
	tbl.innerHTML = "";
	monthAndYear.innerHTML = months[month] + " " + year;
	selectYear.value = year;
	selectMonth.value = month;

	let date = 1;
	for (let i = 0; i < 6; i++) {
		const row = document.createElement("tr");
		for (let j = 0; j < 7; j++) {
			if (i === 0 && j < firstDay) {
				const cell = document.createElement("td");
				const cellText = document.createTextNode("");
				cell.appendChild(cellText);
				row.appendChild(cell);
			} else if (date > daysInMonth(month, year)) {
				break;
			} else {
				const cell = document.createElement("td");
				cell.setAttribute("data-date", date);
				cell.setAttribute("data-month", month + 1);
				cell.setAttribute("data-year", year);
				cell.setAttribute("data-month_name", months[month]);
				cell.className = "date-picker";
				cell.innerHTML = "<span>" + date + "</span";

				if (date === today.getDate() && year === today.getFullYear() && month === today.getMonth()) {
					cell.className = "date-picker selected";
				}

				if (hasEventOnDate(date, month, year)) {
					cell.classList.add("event-marker");
					cell.appendChild(createEventTooltip(date, month, year));
				}

				row.appendChild(cell);
				date++;
			}
		}
		tbl.appendChild(row);
	}

	attachDateClickHandlers();
}

function getEventsOnDate(day, month, year) {
	return events.filter(function (ev) {
		const eventDate = new Date(ev.date);
		return eventDate.getDate() === day && eventDate.getMonth() === month && eventDate.getFullYear() === year;
	});
}

function hasEventOnDate(day, month, year) {
	return getEventsOnDate(day, month, year).length > 0;
}

function daysInMonth(month, year) {
	return 32 - new Date(year, month, 32).getDate();
}

function pad(value) {
	return String(value).padStart(2, "0");
}

function formatForCalendar(date) {
	return (
		date.getFullYear().toString() +
		pad(date.getMonth() + 1) +
		pad(date.getDate()) +
		"T" +
		pad(date.getHours()) +
		pad(date.getMinutes()) +
		"00"
	);
}

function scheduleGoogleMeet(day, month, year) {
	const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || "UTC";
	const start = new Date(year, month, day, 10, 0, 0);
	const end = new Date(year, month, day, 11, 0, 0);

	const params = new URLSearchParams({
		action: "TEMPLATE",
		text: "Google Meet",
		details: "Created from the Superadmin dashboard calendar. Adjust title/time before saving if needed.",
		location: "Google Meet",
		dates: `${formatForCalendar(start)}/${formatForCalendar(end)}`,
		ctz: timezone,
		sf: "true",
		output: "xml",
	});

	const url = `https://calendar.google.com/calendar/render?${params.toString()}`;
	window.open(url, "_blank");
}

function attachDateClickHandlers() {
	const cells = document.querySelectorAll(".date-picker");
	cells.forEach(function (cell) {
		cell.addEventListener("click", function () {
			const day = parseInt(this.getAttribute("data-date"), 10);
			const month = parseInt(this.getAttribute("data-month"), 10) - 1;
			const year = parseInt(this.getAttribute("data-year"), 10);

			document.querySelectorAll(".date-picker.selected").forEach(function (selectedCell) {
				selectedCell.classList.remove("selected");
			});

			this.classList.add("selected");
			scheduleGoogleMeet(day, month, year);
		});
	});
}

$dataHead += "</tr>";
document.getElementById("thead-month").innerHTML = $dataHead;
const monthAndYear = document.getElementById("monthAndYear");
showCalendar(currentMonth, currentYear);