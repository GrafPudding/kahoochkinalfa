const contenidor = document.getElementById("questionari");
const startBtn = document.getElementById("gamebegin");

const loginForm = document.getElementById('loginForm');
const loginUser = document.getElementById('loginUser');
const loginPass = document.getElementById('loginPass');
const logoutBtn = document.getElementById('logoutBtn');
const loginMsg = document.getElementById('loginMsg');

const quizSelect = document.getElementById('quizSelect');
const shuffleChk = document.getElementById('shuffleChk');

let CSRF = null;
async function getCsrf() {
  if (CSRF) return CSRF;
  const r = await fetch('./api/csrf.php', { method: 'POST', credentials: 'same-origin' });
  const j = await r.json();
  CSRF = j.csrf;
  return CSRF;
}
getCsrf().catch(console.error);
loadCatalog();

async function api(url, method = 'GET', body) {
  const headers = { 'Accept': 'application/json' };
  if (method !== 'GET') {
    headers['Content-Type'] = 'application/json';
    headers['X-CSRF'] = await getCsrf();
  }
  const res = await fetch(url, {
    method, headers,
    body: body ? JSON.stringify(body) : undefined,
    credentials: 'same-origin'
  });
  if (!res.ok) {
    const t = await res.text().catch(()=>'');
    throw new Error(`${res.status} ${res.statusText} :: ${t}`);
  }
  return res.json();
}

async function loadCatalog() {
  if (!quizSelect) return;
  quizSelect.innerHTML = '<option value="">Carregant…</option>';
  try {
    const { items } = await api('./api/catalog.php', 'GET');
    if (!items.length) {
      quizSelect.innerHTML = '<option value="">(Sense qüestionaris)</option>';
      startBtn.disabled = true;
      return;
    }
    quizSelect.innerHTML = items.map(i =>
      `<option value="${i.quiz_uid}">${i.title}</option>`
    ).join('');
    startBtn.disabled = false;
  } catch (e) {
    console.error('catalog load failed', e);
    quizSelect.innerHTML = '<option value="">Error carregant</option>';
    startBtn.disabled = true;
  }
}

function setLoggedInUI(user) {
  if (!loginForm) return;

  if (user) {
    loginUser.style.display = 'none';
    loginPass.style.display = 'none';
    loginForm.querySelector('button[type="submit"]').style.display = 'none';
    logoutBtn.style.display = 'inline-block';
    loginMsg.textContent = `Sessió: ${user.role}`;
  } else {
    loginUser.style.display = '';
    loginPass.style.display = '';
    loginForm.querySelector('button[type="submit"]').style.display = '';
    logoutBtn.style.display = 'none';
    loginMsg.textContent = '';
  }

  // --- admin show/hide here ---
  if (user && user.role === 'admin') {
    if (adminArea) {
      adminArea.style.display = 'block';
      loadQuizList().catch(err => console.error('loadQuizList failed', err));
    }
  } else {
    if (adminArea) adminArea.style.display = 'none';
    if (editor) editor.style.display = 'none';
  }
}

// login submit
if (loginForm) {
  loginForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    loginMsg.textContent = '...';
    try {
      const data = await api('./api/login.php', 'POST', {
        username: loginUser.value.trim(),
        password: loginPass.value
      });
      setLoggedInUI(data.user); // { id, role }
      loginMsg.textContent = 'OK';
      loginForm.reset();
    } catch (err) {
      console.error(err);
      setLoggedInUI(null);
      loginMsg.textContent = 'Error inici de sessió';
    }
  });

  logoutBtn.addEventListener('click', async () => {
    try {
      await api('./api/logout.php', 'POST', {});
      setLoggedInUI(null);
      loginMsg.textContent = 'Sessió tancada';
    } catch (err) {
      console.error(err);
      loginMsg.textContent = 'Error sortint';
    }
  });
}

const adminArea = document.getElementById('adminArea');
const quizListDiv = document.getElementById('quizList');
const createQuizForm = document.getElementById('createQuizForm');
const newQuizUid = document.getElementById('newQuizUid');
const newQuizTitle = document.getElementById('newQuizTitle');
const createQuizMsg = document.getElementById('createQuizMsg');

const editor = document.getElementById('editor');
const editQuizUid = document.getElementById('editQuizUid');
const editQuizTitle = document.getElementById('editQuizTitle');
const quizMetaForm = document.getElementById('quizMetaForm');
const quizMetaMsg = document.getElementById('quizMetaMsg');
const backToListBtn = document.getElementById('backToList');
const questionsWrap = document.getElementById('questionsWrap');

let ADMIN = { currentQuizId: null };

/* small util */
function escapeHtml(s) {
  return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

/* list quizzes */
async function loadQuizList() {
  if (!quizListDiv) return;
  quizListDiv.textContent = 'Carregant...';
  try {
    const { items } = await api('./api/quizzes.php', 'GET');
    quizListDiv.innerHTML = items.map(q => `
      <div>
        <strong>${escapeHtml(q.quiz_uid)}</strong> ${q.title ? '('+escapeHtml(q.title)+')' : ''}
        <button data-id="${q.id}" class="btn secondary openQuiz">Editar</button>
      </div>
    `).join('') || '<p>No hi ha qüestionaris.</p>';

    quizListDiv.querySelectorAll('.openQuiz').forEach(btn => {
      btn.addEventListener('click', () => openEditor(+btn.dataset.id));
    });
    editor.style.display = 'none';
    const list = document.getElementById('adminList');
    if (list) list.style.display = 'block';
  } catch (e) {
    quizListDiv.textContent = 'Error carregant';
    console.error('GET /api/quizzes.php failed', e);
  }
}

/* create quiz */
if (createQuizForm) {
  createQuizForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    createQuizMsg.textContent = '...';
    try {
      const r = await api('./api/quizzes.php', 'POST', {
        quiz_uid: newQuizUid.value.trim(),
        title: newQuizTitle.value.trim()
      });
      createQuizMsg.textContent = 'Creat';
      newQuizUid.value = ''; newQuizTitle.value = '';
      loadQuizList();
      loadCatalog();
      // Optionally open editor: openEditor(r.id);
    } catch (err) {
      console.error('POST /api/quizzes.php failed', err);
      createQuizMsg.textContent = 'Error (UID duplicat?)';
    }
  });
}

function renderQuestions(questions) {
  if (!questionsWrap) return;

  questionsWrap.innerHTML = (questions || []).map(q => {
    const a1 = escapeHtml(q.ans?.[1] ?? '');
    const a2 = escapeHtml(q.ans?.[2] ?? '');
    const a3 = escapeHtml(q.ans?.[3] ?? '');
    const a4 = escapeHtml(q.ans?.[4] ?? '');
    const img = q.imatge ? escapeHtml(q.imatge) : '';
    return `
      <form class="qForm" data-qid="${q.id}" style="border:1px solid #ddd;padding:.75rem;margin:.5rem 0;">
        <strong>Pregunta ID ${q.id}</strong><br>
        <textarea name="pregunta" rows="2" cols="60" required>${escapeHtml(q.pregunta)}</textarea><br>
        <input name="imatge" placeholder="Imatge (URL)" value="${img}">
        <div style="margin:.5rem 0;">
          <input name="ans_1" placeholder="Resposta 1" value="${a1}" required>
          <input name="ans_2" placeholder="Resposta 2" value="${a2}" required>
          <input name="ans_3" placeholder="Resposta 3" value="${a3}" required>
          <input name="ans_4" placeholder="Resposta 4" value="${a4}" required>
        </div>
        <label>Correcta (1-4):
          <input name="resposta_correcta" type="number" min="1" max="4" value="${+(q.corr || 1)}" required>
        </label>
        <button class="btn secondary" type="submit">Desar</button>
        <button class="btn secondary delBtn" type="button">Eliminar</button>
        <span class="msg" style="margin-left:.5rem;"></span>
      </form>
    `;
  }).join('') || '<p>Sense preguntes.</p>';

  // Bind SAVE for each question
  questionsWrap.querySelectorAll('.qForm').forEach(form => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const qid = +form.dataset.qid;
      const fd = new FormData(form);
      const payload = {
        action: 'update_question',
        question_id: qid,
        pregunta: fd.get('pregunta'),
        imatge: fd.get('imatge'),
        resposta_correcta: Number(fd.get('resposta_correcta')),
        ans_1: fd.get('ans_1'),
        ans_2: fd.get('ans_2'),
        ans_3: fd.get('ans_3'),
        ans_4: fd.get('ans_4')
      };
      const msgEl = form.querySelector('.msg');
      msgEl.textContent = '...';
      try {
        await api('./api/quiz.php', 'POST', payload);
        msgEl.textContent = 'Desat';
      } catch (err) {
        console.error('update_question failed', err);
        msgEl.textContent = 'Error';
      }
    });

    // Bind DELETE for each question
    const del = form.querySelector('.delBtn');
    if (del) {
      del.addEventListener('click', async () => {
        if (!confirm('Eliminar aquesta pregunta?')) return;
        const qid = +form.dataset.qid;
        const msgEl = form.querySelector('.msg');
        msgEl.textContent = '...';
        try {
          await api('./api/quiz.php', 'POST', { action: 'delete_question', question_id: qid });
          form.remove();
        } catch (err) {
          console.error('delete_question failed', err);
          msgEl.textContent = 'Error';
        }
      });
    }
  });
}

/* open editor for a quiz */
async function openEditor(id) {
  try {
    ADMIN.currentQuizId = id;
    const data = await api(`./api/quiz.php?id=${id}`, 'GET');
    if (editQuizUid)   editQuizUid.value   = data.quiz.quiz_uid || '';
    if (editQuizTitle) editQuizTitle.value = data.quiz.title || '';
    if (quizMetaMsg)   quizMetaMsg.textContent = '';
    renderQuestions(data.questions || []);
    const list = document.getElementById('adminList');
    if (list)  list.style.display = 'none';
    if (editor) editor.style.display = 'block';
  } catch (e) {
    console.error('GET /api/quiz.php?id=.. failed', e);
    alert('No s’ha pogut obrir l’editor');
  }
}

/* save quiz meta */
if(quizMetaForm){
quizMetaForm.addEventListener('submit', async (e) => {
  e.preventDefault();
  quizMetaMsg.textContent = '...';
  try {
    await api('./api/quiz.php', 'POST', {
      action: 'update_quiz',
      id: ADMIN.currentQuizId,
      quiz_uid: editQuizUid.value.trim(),
      title: editQuizTitle.value.trim()
    });
    quizMetaMsg.textContent = 'Desat';
    loadQuizList(); // refresh list so new UID/title shows
  } catch (err) {
    console.error('POST /api/quiz.php update_quiz failed', err);
    quizMetaMsg.textContent = 'Error';
  }
});
}


//back to list
if (backToListBtn) {
  backToListBtn.addEventListener('click', () => {
    if (editor) editor.style.display = 'none';
    loadQuizList();
  });
}

const addQuestionForm= document.getElementById('addQuestionForm');
const aqText   = document.getElementById('aqText');
const aqImage  = document.getElementById('aqImage');
const aqAns1   = document.getElementById('aqAns1');
const aqAns2   = document.getElementById('aqAns2');
const aqAns3   = document.getElementById('aqAns3');
const aqAns4   = document.getElementById('aqAns4');
const aqCorrect= document.getElementById('aqCorrect');
const addQMsg  = document.getElementById('addQMsg');

if (addQuestionForm) {
  addQuestionForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    addQMsg.textContent = '...';
    try {
      await api('./api/quiz.php', 'POST', {
        action: 'add_question',
        quiz_id: ADMIN.currentQuizId,
        pregunta: aqText.value.trim(),
        imatge: aqImage.value.trim(),
        ans_1: aqAns1.value.trim(),
        ans_2: aqAns2.value.trim(),
        ans_3: aqAns3.value.trim(),
        ans_4: aqAns4.value.trim(),
        resposta_correcta: Number(aqCorrect.value)
      });
      addQMsg.textContent = 'Afegida';
      aqText.value=''; aqImage.value='';
      aqAns1.value=''; aqAns2.value=''; aqAns3.value=''; aqAns4.value='';
      aqCorrect.value=1;
      // reload
      await openEditor(ADMIN.currentQuizId);
    } catch (err) {
      console.error('add_question failed', err);
      addQMsg.textContent = 'Error';
    }
  });
}

let quiz = null;
let idx = 0;
let choices = [];
let locked = false;

//timer
const QUESTION_SECONDS = 30;
let remaining = QUESTION_SECONDS;
let tickId = null;

function stopTimer() {
  if (tickId) {
    clearInterval(tickId);
    tickId = null;
  }
}

function startTimer(onExpire) {
  stopTimer();
  remaining = QUESTION_SECONDS;
  updateTimerUI(remaining);

  tickId = setInterval(() => {
    remaining -= 1;
    updateTimerUI(remaining);

    if (remaining <= 0) {
      stopTimer();
      onExpire?.();
    }
  }, 1000);
}

function formatTime(s) {
  const mm = String(Math.floor(s / 60)).padStart(2, "0");
  const ss = String(s % 60).padStart(2, "0");
  return `${mm}:${ss}`;
}

function updateTimerUI(s) {
  const t = contenidor.querySelector("#timer");
  if (t) t.textContent = formatTime(Math.max(0, s));
}

async function finishQuiz() {
  stopTimer(); //make sure timer is off

  contenidor.innerHTML = `
    <section class="resultat">
      <h2>Gràcies!</h2>
      <p>Enviant respostes…</p>
    </section>
  `;

  try {
    const res = await fetch('./api/submit-quiz.php', {
      method: 'POST',
      headers: { 
        'Content-Type': 'application/json',
        'X-CSRF': await getCsrf()
       },
      body: JSON.stringify({ quizId: quiz.quizId, choices })
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const { score, total } = await res.json();

    contenidor.innerHTML = `
      <section class="resultat">
        <h2>Resultat</h2>
        <p>Puntuació: <strong>${score}</strong> / ${total}</p>
        <button id="restartBtn">Tornar a començar</button>
      </section>
    `;
    document.getElementById("restartBtn").addEventListener("click", restartQuiz);
  } catch (e) {
    console.error(e);
    contenidor.innerHTML += `<p style="color:red">No s'ha pogut enviar el resultat, poshol nafig vibecoder de mier*.</p>`;
  }
}

//Render the current question(one at a time)
function renderQuestion() {
  if (!quiz) return;

  if (idx >= quiz.preguntes.length) {
    finishQuiz();
    return;
  }

  const q = quiz.preguntes[idx];

  contenidor.innerHTML = `
    <section class="pregunta">
      <div class="capcalera">
        <small>Pregunta ${idx + 1} de ${quiz.preguntes.length}</small>
        <span class="timer-badge" id="timer">${formatTime(QUESTION_SECONDS)}</span>
      </div>
      <h3>${q.pregunta}</h3>
      ${q.imatge ? `<img src="${q.imatge}" alt="" style="max-width:520px;display:block;margin:.5rem 0;">` : ""}
      <div class="respostes">
        ${q.respostes.map(r => `
          <button type="button" class="resposta" data-rid="${r.id}">
            ${r.etiqueta}
          </button>
        `).join("")}
      </div>
    </section>
  `;
  window.scrollTo({ top: 0, behavior: "smooth" });

  locked = false;

  //Start 30s timer; if it expires, record a "no answer" and advance
  startTimer(() => {
    if (locked) return;
    locked = true;

    choices.push({ qid: q.id, rid: null, timeout: true, t: Date.now() });

    //disable buttons while transitioning
    for (const b of contenidor.querySelectorAll("button.resposta")) b.disabled = true;

    setTimeout(() => {
      idx++;
      renderQuestion();
    }, 300);
  });

  // Click handling
  contenidor.querySelector(".respostes").addEventListener("click", (e) => {
    const btn = e.target.closest("button.resposta");
    if (!btn || locked) return;
    locked = true;
    stopTimer();

    //record choices
    choices.push({ qid: q.id, rid: Number(btn.dataset.rid), t: Date.now() });

    //prevent double clicks while transitioning
    for (const b of contenidor.querySelectorAll("button.resposta")) b.disabled = true;

    setTimeout(() => {
      idx++;
      renderQuestion();
    }, 300);
  });
}

function restartQuiz() {
  stopTimer();
  idx = 0;
  choices = [];
  quiz = null;
  locked = false;

  startBtn.style.display = "inline-block";
  startBtn.disabled = false;
  startBtn.textContent = "Start the game";
  contenidor.innerHTML = "";
}

//Start button: fetch quiz and show first question
startBtn.addEventListener("click", async () => {
  startBtn.disabled = true;
  startBtn.textContent = "Carregant…";

  try {
    const uid = (quizSelect && quizSelect.value) ? quizSelect.value : 'brands-v1';
    const shuf = (shuffleChk && shuffleChk.checked) ? '1' : '0';

    const res = await fetch(`./api/quiz.php?quiz=${encodeURIComponent(uid)}&shuffle=${shuf}`, { cache: 'no-store' });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    quiz = await res.json();

    idx = 0;
    choices = [];
    startBtn.style.display = "none";
    renderQuestion();
  } catch (err) {
    console.error(err);
    startBtn.disabled = false;
    startBtn.textContent = "Start the game";
    contenidor.innerHTML = `<p style="color:red">No s'ha pogut carregar el qüestionari.</p>`;
  }
});