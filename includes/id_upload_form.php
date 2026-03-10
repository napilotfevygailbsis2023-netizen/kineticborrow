<form action="upload_id.php" method="POST" enctype="multipart/form-data" id="id-upload-form">

  <!-- ID TYPE SELECTOR -->
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;margin-bottom:18px">
    <?php foreach(['student'=>['🎓','Student ID','For currently enrolled students'],'senior'=>['👴','Senior ID','For citizens aged 60 and above'],'pwd'=>['♿','PWD ID','For persons with disability']] as $val=>[$icon,$label,$desc]): ?>
    <label style="cursor:pointer">
      <input type="radio" name="id_type" value="<?= $val ?>" style="display:none" class="id-type-radio"/>
      <div class="id-type-card" data-val="<?= $val ?>" style="border:2px solid var(--border);border-radius:12px;padding:14px 10px;text-align:center;transition:all .2s;background:#fff">
        <p style="font-size:26px;margin-bottom:6px"><?= $icon ?></p>
        <p style="font-size:13px;font-weight:600;color:var(--text);margin-bottom:3px"><?= $label ?></p>
        <p style="font-size:11px;color:var(--muted);line-height:1.4"><?= $desc ?></p>
      </div>
    </label>
    <?php endforeach; ?>
  </div>

  <!-- FILE DROP ZONE -->
  <div id="drop-zone" style="border:2px dashed var(--border);border-radius:14px;padding:30px 20px;text-align:center;cursor:pointer;transition:all .2s;background:#FAFAF8;margin-bottom:16px" onclick="document.getElementById('id-file-input').click()">
    <p style="font-size:32px;margin-bottom:8px">📷</p>
    <p style="font-size:14px;font-weight:600;color:var(--text);margin-bottom:4px">Click or drag your ID photo here</p>
    <p style="font-size:12px;color:var(--muted)">JPG, PNG, WEBP — max 5MB</p>
    <div id="file-preview" style="display:none;margin-top:14px">
      <img id="preview-img" src="" style="max-width:100%;max-height:200px;border-radius:10px;object-fit:cover;border:2px solid var(--border)"/>
      <p id="file-name" style="font-size:12px;color:var(--muted);margin-top:8px"></p>
    </div>
  </div>
  <input type="file" id="id-file-input" name="id_image" accept="image/*" style="display:none"/>

  <!-- GUIDELINES -->
  <div style="background:#FDF3E3;border:1px solid #EDD8B0;border-radius:10px;padding:13px 15px;margin-bottom:16px;font-size:12px;color:#7A5C1E">
    <strong>📋 Photo Guidelines:</strong>
    <ul style="margin:6px 0 0 16px;line-height:1.8">
      <li>ID must be valid and not expired</li>
      <li>Photo must be clear and readable</li>
      <li>All four corners of the ID must be visible</li>
      <li>No glare, blur, or obstructions</li>
    </ul>
  </div>

  <button type="submit" id="upload-btn" style="width:100%;background:var(--gold);color:#fff;border:none;padding:13px;border-radius:10px;cursor:pointer;font-size:14px;font-weight:600;font-family:'DM Sans',sans-serif;transition:all .2s;opacity:.6" disabled>
    📤 Submit ID for Verification
  </button>
  <p style="font-size:11px;color:var(--muted);text-align:center;margin-top:10px">Your ID is securely stored and only used for verification purposes.</p>
</form>

<style>
.id-type-card.selected{border-color:var(--gold)!important;background:var(--gold-bg)!important;}
#drop-zone.drag-over{border-color:var(--gold);background:var(--gold-bg);}
</style>
<script>
(function(){
  const radios   = document.querySelectorAll('.id-type-radio');
  const cards    = document.querySelectorAll('.id-type-card');
  const fileInput= document.getElementById('id-file-input');
  const dropZone = document.getElementById('drop-zone');
  const preview  = document.getElementById('file-preview');
  const previewImg=document.getElementById('preview-img');
  const fileName = document.getElementById('file-name');
  const uploadBtn= document.getElementById('upload-btn');
  let typeSelected = false, fileSelected = false;

  function checkReady(){ uploadBtn.disabled=!(typeSelected&&fileSelected); uploadBtn.style.opacity=uploadBtn.disabled?.6:1; }

  cards.forEach((card,i)=>{
    card.addEventListener('click',()=>{
      cards.forEach(c=>c.classList.remove('selected'));
      card.classList.add('selected');
      radios[i].checked = true;
      typeSelected = true; checkReady();
    });
  });

  function showFile(file){
    if(!file||!file.type.startsWith('image/')) return;
    const reader = new FileReader();
    reader.onload = e=>{ previewImg.src=e.target.result; preview.style.display='block'; fileName.textContent=file.name; };
    reader.readAsDataURL(file);
    fileSelected=true; checkReady();
  }

  fileInput.addEventListener('change',()=>showFile(fileInput.files[0]));
  dropZone.addEventListener('dragover', e=>{ e.preventDefault(); dropZone.classList.add('drag-over'); });
  dropZone.addEventListener('dragleave',()=>dropZone.classList.remove('drag-over'));
  dropZone.addEventListener('drop',e=>{ e.preventDefault(); dropZone.classList.remove('drag-over'); const f=e.dataTransfer.files[0]; if(f){ const dt=new DataTransfer(); dt.items.add(f); fileInput.files=dt.files; showFile(f); } });
})();
</script>
