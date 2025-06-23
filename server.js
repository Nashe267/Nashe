const express = require('express');
const multer = require('multer');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const archiver = require('archiver');

const app = express();
const PORT = process.env.PORT || 3000;

const UPLOAD_ROOT = path.join(__dirname, 'uploads');
const SHARE_MAP_FILE = path.join(__dirname, 'shareMap.json');

let shareMap = {};
if (fs.existsSync(SHARE_MAP_FILE)) {
  try {
    shareMap = JSON.parse(fs.readFileSync(SHARE_MAP_FILE));
  } catch (err) {
    console.error('Failed to parse share map:', err);
  }
}

function saveShareMap() {
  fs.writeFileSync(SHARE_MAP_FILE, JSON.stringify(shareMap, null, 2));
}

function ensureDir(dir) {
  fs.mkdirSync(dir, { recursive: true });
}

const storage = multer.diskStorage({
  destination: function (req, file, cb) {
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const userFolder = req.body.folder || 'default';
    const dest = path.join(UPLOAD_ROOT, String(year), month, day, userFolder);
    ensureDir(dest);
    // create share ID if not exist
    const shareKey = path.relative(UPLOAD_ROOT, dest);
    let id = Object.keys(shareMap).find(key => shareMap[key] === shareKey);
    if (!id) {
      id = crypto.randomBytes(8).toString('hex');
      shareMap[id] = shareKey;
      saveShareMap();
    }
    req.shareId = id;
    cb(null, dest);
  },
  filename: function (req, file, cb) {
    cb(null, Date.now() + '-' + file.originalname);
  }
});

const upload = multer({ storage: storage });

app.use(express.static(path.join(__dirname, 'public')));

app.post('/upload', upload.array('files'), (req, res) => {
  const id = req.shareId;
  res.send(`Files uploaded. Share link: <a href="/share/${id}">/share/${id}</a>`);
});

app.get('/share/:id', (req, res) => {
  const sharePath = shareMap[req.params.id];
  if (!sharePath) {
    return res.status(404).send('Invalid share link');
  }
  const dir = path.join(UPLOAD_ROOT, sharePath);
  fs.readdir(dir, (err, files) => {
    if (err) return res.status(500).send('Error reading folder');
    let html = `<h1>Shared files</h1>`;
    html += `<p><a href="/download-all/${req.params.id}" style="font-size:24px;display:inline-block;margin-bottom:1em;">Download All</a></p>`;
    html += `<ul>`;
    files.forEach(f => {
      const encoded = encodeURIComponent(f);
      html += `<li><a href="/download/${req.params.id}/${encoded}">${f}</a></li>`;
    });
    html += '</ul>';
    res.send(html);
  });
});

app.get('/download/:id/:file', (req, res) => {
  const sharePath = shareMap[req.params.id];
  if (!sharePath) {
    return res.status(404).send('Invalid share link');
  }
  const filePath = path.join(UPLOAD_ROOT, sharePath, req.params.file);
  res.download(filePath);
});

app.get('/download-all/:id', (req, res) => {
  const sharePath = shareMap[req.params.id];
  if (!sharePath) {
    return res.status(404).send('Invalid share link');
  }
  const dir = path.join(UPLOAD_ROOT, sharePath);
  res.attachment(`${req.params.id}.zip`);
  const archive = archiver('zip', { zlib: { level: 9 } });
  archive.on('error', err => {
    console.error('Zip error', err);
    res.status(500).send('Error creating zip');
  });
  archive.pipe(res);
  archive.directory(dir, false);
  archive.finalize();
});

app.listen(PORT, () => {
  ensureDir(UPLOAD_ROOT);
  console.log(`Server running on port ${PORT}`);
});

