<?php
// id_upload_form.php — AI-powered ID upload (no manual type selection needed)
$uid = uniqid('idup_');
?>
<div id="<?= $uid ?>_wrap">

  <!-- AI BADGE -->
  <div style="display:flex;align-items:center;gap:10px;background:linear-gradient(120deg,#1C1916,#2E2420);border-radius:12px;padding:13px 16px;margin-bottom:18px;">
    <span style="font-size:22px;">🤖</span>
    <div>
      <div style="font-size:13px;font-weight:700;color:#fff;">AI-Powered ID Verification</div>
      <div style="font-size:11px;color:#AAA;margin-top:1px;">Upload any valid ID — our AI will automatically detect the type, validate it, and grant your discount instantly.</div>
    </div>
  </div>

  <!-- WHAT AI CHECKS -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:18px;">
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:12px;color:var(--text2);display:flex;gap:8px;align-items:center;">
      <span>🎓</span> Student ID → 20% off
    </div>
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:12px;color:var(--text2);display:flex;gap:8px;align-items:center;">
      <span>👴</span> Senior Citizen → 20% off
    </div>
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:12px;color:var(--text2);display:flex;gap:8px;align-items:center;">
      <span>♿</span> PWD ID → 20% off
    </div>
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:10px;padding:10px 12px;font-size:12px;color:var(--text2);display:flex;gap:8px;align-items:center;">
      <span>🪪</span> Gov't ID → Verified ✓
    </div>
  </div>

  <form action="upload_id.php" method="POST" enctype="multipart/form-data" id="<?= $uid ?>_form" onsubmit="return <?= $uid ?>_submit()">

    <!-- DROP ZONE -->
    <div id="<?= $uid ?>_drop"
      style="border:2px dashed var(--border);border-radius:14px;padding:36px 20px;text-align:center;cursor:pointer;transition:all .2s;background:#FAFAF8;margin-bottom:14px;"
      onclick="document.getElementById('<?= $uid ?>_file').click()">
      <p style="font-size:40px;margin-bottom:10px" id="<?= $uid ?>_dropicon">📷</p>
      <p style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px">Click or drag your ID photo here</p>
      <p style="font-size:12px;color:var(--muted)">JPG, PNG, WEBP — max 5MB · Any valid Philippine ID accepted</p>
      <div id="<?= $uid ?>_preview" style="display:none;margin-top:14px">
        <img id="<?= $uid ?>_pimg" src="" style="max-width:100%;max-height:200px;border-radius:10px;object-fit:cover;border:2px solid var(--border)"/>
        <p id="<?= $uid ?>_pname" style="font-size:12px;color:var(--muted);margin-top:8px"></p>
      </div>
    </div>
    <input type="file" id="<?= $uid ?>_file" name="id_image" accept="image/*" style="display:none"/>

    <!-- GUIDELINES -->
    <div style="background:#FDF3E3;border:1px solid #EDD8B0;border-radius:10px;padding:12px 14px;margin-bottom:16px;font-size:12px;color:#7A5C1E;">
      <strong>📋 For best AI accuracy:</strong>
      <ul style="margin:5px 0 0 16px;line-height:1.9">
        <li>All 4 corners of your ID must be visible</li>
        <li>No glare, blur, or shadows covering text</li>
        <li>ID must be valid and not expired</li>
        <li>Lay the ID flat on a plain surface</li>
      </ul>
    </div>

    <!-- SUBMIT BUTTON -->
    <button type="submit" id="<?= $uid ?>_btn"
      style="width:100%;background:var(--gold);color:#fff;border:none;padding:13px;border-radius:10px;cursor:pointer;font-size:14px;font-weight:700;font-family:'DM Sans',sans-serif;transition:all .2s;opacity:.5;display:flex;align-items:center;justify-content:center;gap:8px;"
      disabled>
      <span id="<?= $uid ?>_btn_icon">🤖</span>
      <span id="<?= $uid ?>_btn_txt">Analyze & Verify with AI</span>
    </button>
    <p style="font-size:11px;color:var(--muted);text-align:center;margin-top:10px;line-height:1.5">
      🔒 Your ID is securely processed. AI analyzes the image instantly — results in seconds.
    </p>
  </form>
</div>

<style>
#<?= $uid ?>_drop.drag-over{border-color:var(--gold)!important;background:var(--gold-bg)!important;}
</style>

<script>
(function(){
  const uid  = '<?= $uid ?>';
  const file  = document.getElementById(uid+'_file');
  const drop  = document.getElementById(uid+'_drop');
  const btn   = document.getElementById(uid+'_btn');
  const btnTxt= document.getElementById(uid+'_btn_txt');
  const btnIco= document.getElementById(uid+'_btn_icon');

  function showFile(f) {
    if (!f || !f.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById(uid+'_pimg').src = e.target.result;
      document.getElementById(uid+'_pname').textContent = f.name + ' (' + (f.size/1024).toFixed(0) + ' KB)';
      document.getElementById(uid+'_preview').style.display = 'block';
      document.getElementById(uid+'_dropicon').textContent = '✅';
    };
    reader.readAsDataURL(f);
    btn.disabled = false;
    btn.style.opacity = '1';
    btnTxt.textContent = 'Analyze & Verify with AI';
    btnIco.textContent = '🤖';
  }

  file.addEventListener('change', () => showFile(file.files[0]));
  drop.addEventListener('dragover',  e => { e.preventDefault(); drop.classList.add('drag-over'); });
  drop.addEventListener('dragleave', () => drop.classList.remove('drag-over'));
  drop.addEventListener('drop', e => {
    e.preventDefault(); drop.classList.remove('drag-over');
    const f = e.dataTransfer.files[0];
    if (f) { const dt = new DataTransfer(); dt.items.add(f); file.files = dt.files; showFile(f); }
  });

  window[uid+'_submit'] = function() {
    btn.disabled = true;
    btn.style.background = '#666';
    btnIco.textContent = '⏳';
    btnTxt.textContent = 'AI is analyzing your ID...';
    return true;
  };
})();
</script>
