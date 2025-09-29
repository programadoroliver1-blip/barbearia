const timerDisplay = document.getElementById("timerDisplay");
const startBtn = document.getElementById("startBtn");
const stopBtn = document.getElementById("stopBtn");
const resetBtn = document.getElementById("resetBtn");
const saveBtn = document.getElementById("saveBtn");
const recordedTimeInput = document.getElementById("recordedTime");
const competitorInput = document.getElementById("competitorName");
const clearHistoryBtn = document.getElementById("clearHistoryBtn");
const showAllAttemptsCheckbox = document.getElementById("showAllAttempts");
const scoreboardBody = document.getElementById("scoreboardBody");
const historySection = document.getElementById("history");
const historyList = document.getElementById("historyList");
const recordForm = document.getElementById("recordForm");
const historyItemTemplate = document.getElementById("historyItemTemplate");
const STORAGE_KEY = "cubing-timer-scoreboard";

let startTime = 0;
let elapsedBeforePause = 0;
let animationFrameId = null;
let isRunning = false;
let lastRecordedTime = 0;

const competitors = new Map();
const allAttempts = [];

function formatTime(milliseconds) {
  if (!Number.isFinite(milliseconds)) return "00:00.000";
  const totalMs = Math.max(0, Math.floor(milliseconds));
  const minutes = Math.floor(totalMs / 60000);
  const seconds = Math.floor((totalMs % 60000) / 1000);
  const ms = totalMs % 1000;
  return `${String(minutes).padStart(2, "0")}:${String(seconds).padStart(2, "0")}.${String(ms).padStart(3, "0")}`;
}

function updateDisplay(timeMs) {
  timerDisplay.textContent = formatTime(timeMs);
}

function tick() {
  const now = performance.now();
  const elapsed = now - startTime + elapsedBeforePause;
  updateDisplay(elapsed);
  animationFrameId = requestAnimationFrame(tick);
}

function startTimer() {
  if (isRunning) return;
  isRunning = true;
  startTime = performance.now();
  animationFrameId = requestAnimationFrame(tick);
  startBtn.textContent = "Retomar";
  startBtn.disabled = true;
  stopBtn.disabled = false;
  resetBtn.disabled = false;
  saveBtn.disabled = true;
}

function stopTimer() {
  if (!isRunning) return;
  isRunning = false;
  cancelAnimationFrame(animationFrameId);
  const now = performance.now();
  elapsedBeforePause += now - startTime;
  updateDisplay(elapsedBeforePause);
  lastRecordedTime = elapsedBeforePause;
  recordedTimeInput.value = formatTime(lastRecordedTime);
  saveBtn.disabled = false;
  stopBtn.disabled = true;
  startBtn.disabled = false;
}

function resetTimer() {
  isRunning = false;
  cancelAnimationFrame(animationFrameId);
  startTime = 0;
  elapsedBeforePause = 0;
  lastRecordedTime = 0;
  updateDisplay(0);
  recordedTimeInput.value = "00:00.000";
  saveBtn.disabled = true;
  stopBtn.disabled = true;
  resetBtn.disabled = true;
  startBtn.textContent = "Iniciar";
  startBtn.disabled = false;
}

function ensureCompetitor(name) {
  if (!competitors.has(name)) {
    competitors.set(name, {
      attempts: [],
      best: Infinity,
      last: null,
    });
  }
  return competitors.get(name);
}

function recordAttempt(name, timeMs) {
  const competitor = ensureCompetitor(name);
  competitor.attempts.push(timeMs);
  competitor.last = timeMs;
  if (timeMs < competitor.best) {
    competitor.best = timeMs;
  }
  allAttempts.push({
    name,
    timeMs,
    recordedAt: new Date(),
  });
}

function persistState() {
  try {
    const serialized = {
      competitors: Array.from(competitors.entries()).map(([name, data]) => ({
        name,
        attempts: data.attempts,
        best: data.best,
        last: data.last,
      })),
      allAttempts: allAttempts.map((attempt) => ({
        name: attempt.name,
        timeMs: attempt.timeMs,
        recordedAt: attempt.recordedAt.toISOString(),
      })),
    };
    localStorage.setItem(STORAGE_KEY, JSON.stringify(serialized));
  } catch (error) {
    console.error("Não foi possível salvar o placar localmente.", error);
  }
}

function hydrateFromStorage() {
  const raw = localStorage.getItem(STORAGE_KEY);
  if (!raw) {
    updateScoreboard();
    updateHistory();
    return;
  }

  try {
    const parsed = JSON.parse(raw);
    if (Array.isArray(parsed?.competitors)) {
      parsed.competitors.forEach((entry) => {
        if (!entry?.name) return;
        const competitor = ensureCompetitor(entry.name);
        competitor.attempts = Array.isArray(entry.attempts) ? entry.attempts.slice() : [];
        competitor.best = Number.isFinite(entry.best) ? entry.best : Infinity;
        competitor.last = Number.isFinite(entry.last) ? entry.last : null;
      });
    }

    if (Array.isArray(parsed?.allAttempts)) {
      parsed.allAttempts.forEach((attempt) => {
        if (!attempt?.name || !Number.isFinite(attempt.timeMs)) return;
        allAttempts.push({
          name: attempt.name,
          timeMs: attempt.timeMs,
          recordedAt: attempt.recordedAt ? new Date(attempt.recordedAt) : new Date(),
        });
      });
    }

    updateScoreboard();
    updateHistory();

    if (competitors.size > 0) {
      resetBtn.disabled = false;
      clearHistoryBtn.disabled = false;
    }
  } catch (error) {
    console.error("Não foi possível carregar o placar salvo.", error);
    localStorage.removeItem(STORAGE_KEY);
    updateScoreboard();
    updateHistory();
  }
}

function updateScoreboard() {
  const rows = Array.from(competitors.entries())
    .map(([name, data]) => ({ name, ...data }))
    .filter(({ attempts }) => attempts.length > 0)
    .sort((a, b) => a.best - b.best || a.name.localeCompare(b.name));

  scoreboardBody.innerHTML = "";

  if (rows.length === 0) {
    scoreboardBody.innerHTML = `<tr class="scoreboard__empty"><td colspan="5">Nenhum tempo registrado ainda. Que tal iniciar uma disputa?</td></tr>`;
    clearHistoryBtn.disabled = true;
    return;
  }

  clearHistoryBtn.disabled = false;

  rows.forEach((row, index) => {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${index + 1}</td>
      <td>${row.name}</td>
      <td>${formatTime(row.best)}</td>
      <td>${row.last ? formatTime(row.last) : "-"}</td>
      <td>${row.attempts.length}</td>
    `;
    scoreboardBody.append(tr);
  });
}

function updateHistory() {
  const showAll = showAllAttemptsCheckbox.checked;
  historyList.innerHTML = "";

  if (!showAll) {
    historySection.hidden = true;
    return;
  }

  historySection.hidden = false;
  const fragment = document.createDocumentFragment();

  allAttempts
    .slice()
    .sort((a, b) => b.recordedAt - a.recordedAt)
    .forEach((attempt, index) => {
      const item = historyItemTemplate.content.cloneNode(true);
      item.querySelector(".history__time").textContent = `${index + 1}º · ${formatTime(attempt.timeMs)}`;
      item.querySelector(".history__name").textContent = `${attempt.name} • ${attempt.recordedAt.toLocaleTimeString("pt-BR", {
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
      })}`;
      fragment.appendChild(item);
    });

  historyList.appendChild(fragment);
}

function handleSave(event) {
  event.preventDefault();
  const name = competitorInput.value.trim();

  if (!name) {
    competitorInput.focus();
    return;
  }

  if (lastRecordedTime <= 0) {
    return;
  }

  recordAttempt(name, lastRecordedTime);
  updateScoreboard();
  updateHistory();
  persistState();

  competitorInput.value = "";
  competitorInput.focus();
  saveBtn.disabled = true;
}

function clearHistory() {
  competitors.clear();
  allAttempts.length = 0;
  updateScoreboard();
  updateHistory();
  resetTimer();
  try {
    localStorage.removeItem(STORAGE_KEY);
  } catch (error) {
    console.error("Não foi possível limpar o placar salvo.", error);
  }
}

startBtn.addEventListener("click", () => {
  if (!isRunning) {
    startTimer();
  }
});

stopBtn.addEventListener("click", () => {
  stopTimer();
});

resetBtn.addEventListener("click", () => {
  resetTimer();
});

recordForm.addEventListener("submit", handleSave);

showAllAttemptsCheckbox.addEventListener("change", updateHistory);

clearHistoryBtn.addEventListener("click", () => {
  const confirmation = confirm(
    "Tem certeza de que deseja apagar todo o placar? Essa ação não poderá ser desfeita."
  );
  if (!confirmation) return;
  clearHistory();
});

hydrateFromStorage();
updateDisplay(0);
