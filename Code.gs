/**
 * العكريشي – الخادم على حسابك في Google (Apps Script + Drive)
 * الصق هذا الملف كاملًا في script.google.com ثم انشره كتطبيق ويب.
 */
const ROOT_NAME = 'العكريشي';
const P_ = () => PropertiesService.getScriptProperties();
const C_ = () => CacheService.getScriptCache();

function doGet() { return ContentService.createTextOutput('خادم العكريشي يعمل ✓'); }

function doPost(e) {
  let r;
  try { r = route_(JSON.parse(e.postData.contents)); }
  catch (err) { r = { e: String(err && err.message ? err.message : err) }; }
  return ContentService.createTextOutput(JSON.stringify(r)).setMimeType(ContentService.MimeType.JSON);
}

/* ---------- أدوات ---------- */
function s_(v, n) { v = String(v == null ? '' : v).trim(); return v.slice(0, n || 2000); }
function idc_(v) { v = String(v || '').replace(/[^A-Za-z0-9]/g, '').slice(0, 16); return v || Utilities.getUuid().replace(/-/g, '').slice(0, 8); }
function norm_(v) {
  v = String(v || '').replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
  return v.toUpperCase().replace(/[^A-Z0-9]/g, '');
}
function hash_(p) {
  const salt = P_().getProperty('salt');
  return Utilities.base64Encode(Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, salt + String(p)));
}
function init_() {
  const p = P_();
  if (!p.getProperty('salt')) {
    p.setProperty('salt', Utilities.getUuid());
    p.setProperty('pwh', hash_('012345'));
    p.setProperty('defpw', '1');
  }
}
function root_() { const it = DriveApp.getFoldersByName(ROOT_NAME); return it.hasNext() ? it.next() : DriveApp.createFolder(ROOT_NAME); }
function vids_() { const r = root_(), it = r.getFoldersByName('videos'); return it.hasNext() ? it.next() : r.createFolder('videos'); }
function dbfile_() {
  const p = P_(), id = p.getProperty('dbid');
  if (id) { try { return DriveApp.getFileById(id); } catch (e) {} }
  const f = root_().createFile('db.json', '{}');
  p.setProperty('dbid', f.getId());
  return f;
}
function load_() {
  const c = C_().get('db');
  if (c) return JSON.parse(c);
  let db = {};
  try { db = JSON.parse(dbfile_().getBlob().getDataAsString() || '{}'); } catch (e) { db = {}; }
  if (!db.courses) {
    db = { settings: { name: 'العكريشي', tag: 'يَا نفسُ عِيشي مَع العَكريشي', pay: '01151045032', wa: '201151045032',
      legal: { terms: 'للاستخدام الشخصي فقط ويُمنع تسجيل الدروس أو مشاركة كود التفعيل. [عدّل النص]', privacy: 'نحفظ بيانات الموقع في حساب Google الخاص بالمدير ولا نبيعها. [عدّل النص]', refund: '[اكتب سياسة الاسترجاع]' } },
      courses: [] };
    save_(db);
  }
  return db;
}
function save_(db) {
  const t = JSON.stringify(db);
  dbfile_().setContent(t);
  if (t.length < 90000) C_().put('db', t, 600); else C_().remove('db');
}
function isAdm_(tok) { return !!tok && !!C_().get('t' + tok); }
function need_(q) { if (!isAdm_(q.tok)) throw new Error('غير مصرّح، سجّل دخول المدير'); }
function codesOk_(c, own) { return (c.codes || []).some(k => own.indexOf(k.code) >= 0); }
function ownCodes_(q) { return (Array.isArray(q.codes) ? q.codes : []).map(norm_).filter(x => x.length >= 8); }
function findC_(db, id) { return db.courses.filter(c => c.id === id)[0] || null; }
function findL_(c, id) { return c.lessons.filter(l => l.id === id)[0] || null; }

/* ---------- المسارات ---------- */
function route_(q) {
  init_();
  switch (q.a) {
    case 'state': return state_(q);
    case 'login': return login_(q);
    case 'logout': C_().remove('t' + q.tok); return { ok: 1 };
    case 'admin': { need_(q); const db = load_(); return { settings: db.settings, courses: db.courses, defpw: P_().getProperty('defpw') === '1' }; }
    case 'save': { need_(q); return save2_(q); }
    case 'pw': { need_(q); return pw_(q); }
    case 'redeem': return redeem_(q);
    case 'quiz': return quiz_(q);
    case 'upinit': { need_(q); return upinit_(q); }
    case 'upchunk': { need_(q); return upchunk_(q); }
    default: throw new Error('طلب غير معروف');
  }
}

function state_(q) {
  const db = load_(), adm = isAdm_(q.tok), own = ownCodes_(q);
  const o = { settings: db.settings, admin: adm, courses: [] };
  db.courses.forEach(c => {
    const ok = adm || codesOk_(c, own);
    const r = { id: c.id, title: c.title, grade: c.grade, price: c.price, desc: c.desc, unlocked: ok, lessons: [] };
    c.lessons.forEach(l => {
      const open = ok || !!l.free;
      const x = { id: l.id, title: l.title, unit: l.unit, free: !!l.free, open: open };
      if (open) {
        x.video = l.video; x.pdf = l.pdf; x.vfile = l.vfile || ''; x.pfile = l.pfile || '';
        x.quiz = (l.quiz || []).map(z => ({ q: z.q, o: z.o }));
      }
      r.lessons.push(x);
    });
    o.courses.push(r);
  });
  return o;
}

function login_(q) {
  const p = P_();
  if (Date.now() < Number(p.getProperty('rl_u') || 0)) throw new Error('محاولات كثيرة، حاول بعد 15 دقيقة');
  if (hash_(q.password || '') === p.getProperty('pwh')) {
    p.deleteProperty('rl_n');
    const tok = Utilities.getUuid() + Utilities.getUuid().replace(/-/g, '');
    C_().put('t' + tok, '1', 21600);
    return { tok: tok };
  }
  const n = Number(p.getProperty('rl_n') || 0) + 1;
  if (n >= 5) { p.setProperty('rl_u', String(Date.now() + 900000)); p.setProperty('rl_n', '0'); } else p.setProperty('rl_n', String(n));
  throw new Error('كلمة السر غير صحيحة');
}

function pw_(q) {
  const p = P_();
  if (hash_(q.old || '') !== p.getProperty('pwh')) throw new Error('كلمة السر الحالية غير صحيحة');
  const n = String(q.new || '');
  if (n.length < 6) throw new Error('كلمة السر الجديدة قصيرة (6 أحرف على الأقل)');
  p.setProperty('pwh', hash_(n)); p.setProperty('defpw', '0');
  return { ok: 1 };
}

function redeem_(q) {
  const code = norm_(q.code);
  if (code.length < 8) throw new Error('الكود غير صحيح');
  const db = load_();
  for (const c of db.courses) if ((c.codes || []).some(k => k.code === code)) return { ok: 1, course: c.id };
  throw new Error('الكود غير صحيح');
}

function quiz_(q) {
  const db = load_(), c = findC_(db, String(q.c || '')), l = c ? findL_(c, String(q.l || '')) : null;
  if (!l) throw new Error('غير مسموح');
  if (!(isAdm_(q.tok) || l.free || codesOk_(c, ownCodes_(q)))) throw new Error('غير مسموح');
  const ans = Array.isArray(q.ans) ? q.ans : [], w = [];
  (l.quiz || []).forEach((z, i) => { if (Number(ans[i] == null ? -1 : ans[i]) !== Number(z.a)) w.push(i); });
  return { score: (l.quiz || []).length - w.length, total: (l.quiz || []).length, wrong: w };
}

function save2_(q) {
  const lock = LockService.getScriptLock(); lock.waitLock(20000);
  try {
    const db = load_(), old = {};
    db.courses.forEach(c => (c.codes || []).forEach(k => { old[k.code] = k; }));
    const refs = {}, cs = [];
    (q.courses || []).forEach(c => {
      const L = [], K = [];
      (c.lessons || []).forEach(l => {
        const Q = [];
        (l.quiz || []).forEach(z => {
          const o = (z.o || []).map(x => s_(x)); if (o.length < 2) return;
          const a = Number(z.a) || 0;
          Q.push({ q: s_(z.q), o: o, a: (a >= 0 && a < o.length) ? a : 0 });
        });
        const vf = /^[\w-]{10,80}$/.test(l.vfile || '') ? l.vfile : '', pf = /^[\w-]{10,80}$/.test(l.pfile || '') ? l.pfile : '';
        if (vf) refs[vf] = 1; if (pf) refs[pf] = 1;
        L.push({ id: idc_(l.id), title: s_(l.title, 200), unit: s_(l.unit, 200), free: !!l.free, video: s_(l.video, 500), pdf: s_(l.pdf, 500),
          vfile: vf, vname: s_(l.vname, 200), pfile: pf, pname: s_(l.pname, 200), quiz: Q });
      });
      (c.codes || []).forEach(k => {
        const code = norm_(k.code); if (code.length < 8) return;
        K.push({ code: code, label: s_(k.label, 100), at: Number(k.at) || Date.now(), uses: 0 });
      });
      cs.push({ id: idc_(c.id), title: s_(c.title, 200), grade: s_(c.grade, 100), price: Math.max(0, Math.floor(Number(c.price) || 0)),
        desc: s_(c.desc, 1000), lessons: L, codes: K });
    });
    const st = q.settings || {}, lg = st.legal || {};
    db.settings = { name: s_(st.name, 100), tag: s_(st.tag, 200), pay: s_(st.pay, 30), wa: String(st.wa || '').replace(/\D/g, ''),
      legal: { terms: s_(lg.terms, 3000), privacy: s_(lg.privacy, 3000), refund: s_(lg.refund, 3000) } };
    db.courses = cs;
    save_(db);
    try {
      const it = vids_().getFiles();
      while (it.hasNext()) { const f = it.next(); if (!refs[f.getId()] && (Date.now() - f.getDateCreated().getTime()) > 7200000) f.setTrashed(true); }
    } catch (e) {}
    return { ok: 1 };
  } finally { lock.releaseLock(); }
}

/* ---------- رفع الفيديو إلى Drive (رفع متقطع) ---------- */
function upinit_(q) {
  const size = Number(q.size), mime = s_(q.mime, 100) || 'application/octet-stream';
  if (!(size > 0) || size > 1500 * 1048576) throw new Error('حجم الملف غير مناسب (الحد 1.5 جيجا)');
  const r = UrlFetchApp.fetch('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&fields=id', {
    method: 'post', contentType: 'application/json; charset=UTF-8', muteHttpExceptions: true,
    headers: { Authorization: 'Bearer ' + ScriptApp.getOAuthToken(), 'X-Upload-Content-Type': mime, 'X-Upload-Content-Length': String(size) },
    payload: JSON.stringify({ name: s_(q.name, 150) || 'file', parents: [vids_().getId()] })
  });
  if (r.getResponseCode() !== 200) throw new Error('تعذّر بدء الرفع على Drive. تأكد من إضافة خدمة Drive API. ' + r.getContentText().slice(0, 150));
  const h = r.getAllHeaders(), loc = h['Location'] || h['location'];
  const id = Utilities.getUuid();
  C_().put('u' + id, loc, 21600);
  return { id: id };
}
function upchunk_(q) {
  const loc = C_().get('u' + q.id);
  if (!loc) throw new Error('انتهت جلسة الرفع، أعد المحاولة');
  const r = UrlFetchApp.fetch(loc, {
    method: 'put', contentType: 'application/octet-stream', muteHttpExceptions: true, followRedirects: false,
    headers: { 'Content-Range': 'bytes ' + Number(q.start) + '-' + Number(q.end) + '/' + Number(q.total) },
    payload: Utilities.base64Decode(String(q.data))
  });
  const c = r.getResponseCode();
  if (c === 200 || c === 201) {
    const id = JSON.parse(r.getContentText()).id;
    DriveApp.getFileById(id).setSharing(DriveApp.Access.ANYONE_WITH_LINK, DriveApp.Permission.VIEW);
    return { file: id };
  }
  if (c === 308) {
    const h = r.getAllHeaders(), rg = h['Range'] || h['range'];
    return { next: rg ? Number(String(rg).split('-')[1]) + 1 : 0 };
  }
  throw new Error('فشل الرفع إلى Drive (' + c + ')');
}
