// Storage for the group-photo page, runs free on Google Apps Script.
// No password: anyone with the page can edit.
const CHUNK = 3000; // characters per stored piece (Apps Script limits each piece to ~9 KB)

function defaults_() {
  return [12, 32, 52, 72].map(function (x, i) {
    return { id: 's' + (i + 1), name: 'Student-' + (i + 1), x: x, y: 30, w: 10, h: 34,
             job: '', current: '', permanent: '', mobile: '' };
  });
}
function read_() {
  const p = PropertiesService.getScriptProperties();
  const n = parseInt(p.getProperty('n') || '0', 10);
  if (!n) return defaults_();
  let s = '';
  for (let i = 0; i < n; i++) s += p.getProperty('c' + i) || '';
  try { const d = JSON.parse(s); return (Array.isArray(d) && d.length) ? d : defaults_(); }
  catch (e) { return defaults_(); }
}
function write_(d) {
  const p = PropertiesService.getScriptProperties();
  const old = parseInt(p.getProperty('n') || '0', 10);
  const s = JSON.stringify(d), o = {}; let n = 0;
  for (let i = 0; i < s.length; i += CHUNK) o['c' + (n++)] = s.substr(i, CHUNK);
  o.n = String(n);
  p.setProperties(o);
  for (let i = n; i < old; i++) p.deleteProperty('c' + i);
}
function out_(obj) {
  return ContentService.createTextOutput(JSON.stringify(obj)).setMimeType(ContentService.MimeType.JSON);
}
const txt_ = function (v, n) { return String(v == null ? '' : v).trim().slice(0, n || 120); };
const num_ = function (v, min, max) { v = Math.round(parseFloat(v) * 100) / 100; return isNaN(v) ? min : Math.max(min, Math.min(max, v)); };

function doGet() { return out_({ students: read_() }); }

function doPost(e) {
  const lock = LockService.getScriptLock();
  lock.waitLock(15000);
  try {
    const inp = JSON.parse(e.postData.contents || '{}');
    let d = read_();

    if (inp.action === 'details') {            // fill in details
      d.forEach(function (s) {
        if (s.id === String(inp.id)) ['job', 'current', 'permanent', 'mobile'].forEach(function (k) {
          if (inp[k] !== undefined) s[k] = txt_(inp[k]);
        });
      });
      write_(d); return out_({ students: d });
    }
    if (inp.action === 'layout' && Array.isArray(inp.students)) {   // add / move / resize / rename / remove
      const by = {}; d.forEach(function (s) { by[s.id] = s; });
      const nd = [];
      inp.students.slice(0, 100).forEach(function (n) {
        const id = txt_(n.id, 40); if (!id) return;
        const b = by[id] || { job: '', current: '', permanent: '', mobile: '' };
        nd.push({ id: id, name: txt_(n.name, 60) || 'Student',
          x: num_(n.x, 0, 100), y: num_(n.y, 0, 100), w: num_(n.w, 2, 100), h: num_(n.h, 2, 100),
          job: b.job, current: b.current, permanent: b.permanent, mobile: b.mobile });
      });
      if (nd.length) { write_(nd); d = nd; }
      return out_({ students: d });
    }
    return out_({ error: 'Unknown action' });
  } catch (err) {
    return out_({ error: String(err) });
  } finally {
    lock.releaseLock();
  }
}
