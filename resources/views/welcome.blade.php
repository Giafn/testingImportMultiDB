<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Import Excel Aset Daerah</title>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Custom Color Theme -->
  <style>
    :root {
      --bs-primary: #2e7d32;   /* hijau utama modern */
      --bs-secondary: #388e3c; /* variasi hijau */
      --bs-success: #43a047;   /* hijau segar */
      --bs-info: #00796b;      /* hijau kebiruan */
      --bs-warning: #fbc02d;
      --bs-danger: #e53935;
      --bs-light: #f8f9fa;
      --bs-dark: #212121;
    }

    body {
      background-color: #f4f6f4;
    }

    .nav-tabs .nav-link.active {
      background-color: var(--bs-primary);
      color: #fff !important;
      border-color: var(--bs-primary) var(--bs-primary) #fff;
    }

    .nav-tabs .nav-link {
      color: var(--bs-primary);
      font-weight: 500;
    }

    .drop-zone {
      border: 2px dashed var(--bs-primary);
      border-radius: 10px;
      padding: 40px;
      text-align: center;
      color: var(--bs-primary);
      background-color: #e8f5e9;
      cursor: pointer;
      transition: 0.2s ease;
    }

    .drop-zone.dragover {
      background-color: #c8e6c9;
      border-color: var(--bs-success);
      color: var(--bs-dark);
    }
  </style>
</head>
<body>
  <div class="container py-5">
    <h2 class="text-center mb-4 text-primary fw-bold">📊 Import Excel Aset Daerah</h2>
    <p class="text-center text-muted mb-5">Silakan pilih kategori KIB (A–H) lalu unggah file Excel dengan drag & drop.</p>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <!-- Tabs Kategori -->
    <ul class="nav nav-tabs mb-3" id="kibTab" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="kibA-tab" data-bs-toggle="tab" data-bs-target="#kibA" type="button" role="tab">KIB A</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="kibB-tab" data-bs-toggle="tab" data-bs-target="#kibB" type="button" role="tab">KIB B</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="kibC-tab" data-bs-toggle="tab" data-bs-target="#kibC" type="button" role="tab">KIB C</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="kibD-tab" data-bs-toggle="tab" data-bs-target="#kibD" type="button" role="tab">KIB D</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="kibE-tab" data-bs-toggle="tab" data-bs-target="#kibE" type="button" role="tab">KIB E</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="kibF-tab" data-bs-toggle="tab" data-bs-target="#kibF" type="button" role="tab">KIB F</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="kibG-tab" data-bs-toggle="tab" data-bs-target="#kibG" type="button" role="tab">KIB G</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="kibH-tab" data-bs-toggle="tab" data-bs-target="#kibH" type="button" role="tab">KIB H</button>
      </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="kibTabContent">
      <!-- Loop KIB A-H -->
      <div class="tab-pane fade show active" id="kibA" role="tabpanel">
        <form action="{{ route('import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="drop-zone" id="dropA">
              <p>📂 Tarik & letakkan file Excel KIB A di sini atau klik untuk memilih</p>
              <input type="file" name="file" hidden accept=".xlsx,.xls">
              <input type="hidden" name="kategori" value="A">
            </div>
            <button type="submit" class="btn btn-primary mt-3">Import</button>
        </form>
      </div>
      <div class="tab-pane fade" id="kibB" role="tabpanel">
        <form action="{{ route('import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="drop-zone" id="dropB">
              <p>📂 Tarik & letakkan file Excel KIB B di sini atau klik untuk memilih</p>
              <input type="file" name="file" hidden accept=".xlsx,.xls">
            </div>
            <input type="hidden" name="kategori" value="B">
            <button type="submit" class="btn btn-primary mt-3">Import</button>
        </form>
      </div>
      <div class="tab-pane fade" id="kibC" role="tabpanel">
         <form action="{{ route('import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="drop-zone" id="dropC">
              <p>📂 Tarik & letakkan file Excel KIB C di sini atau klik untuk memilih</p>
              <input type="file" name="fileC" hidden accept=".xlsx,.xls">
            </div>
            <input type="hidden" name="kategori" value="C">
            <button type="submit" class="btn btn-primary mt-3">Import</button>
        </form>
      </div>
      <div class="tab-pane fade" id="kibD" role="tabpanel">
        <div class="drop-zone" id="dropD">
          <p>📂 Tarik & letakkan file Excel KIB D di sini atau klik untuk memilih</p>
          <input type="file" name="fileD" hidden accept=".xlsx,.xls">
        </div>
      </div>
      <div class="tab-pane fade" id="kibE" role="tabpanel">
        <div class="drop-zone" id="dropE">
          <p>📂 Tarik & letakkan file Excel KIB E di sini atau klik untuk memilih</p>
          <input type="file" name="fileE" hidden accept=".xlsx,.xls">
        </div>
      </div>
      <div class="tab-pane fade" id="kibF" role="tabpanel">
        <div class="drop-zone" id="dropF">
          <p>📂 Tarik & letakkan file Excel KIB F di sini atau klik untuk memilih</p>
          <input type="file" name="fileF" hidden accept=".xlsx,.xls">
        </div>
      </div>
      <div class="tab-pane fade" id="kibG" role="tabpanel">
        <div class="drop-zone" id="dropG">
          <p>📂 Tarik & letakkan file Excel KIB G di sini atau klik untuk memilih</p>
          <input type="file" name="fileG" hidden accept=".xlsx,.xls">
        </div>
      </div>
      <div class="tab-pane fade" id="kibH" role="tabpanel">
        <div class="drop-zone" id="dropH">
          <p>📂 Tarik & letakkan file Excel KIB H di sini atau klik untuk memilih</p>
          <input type="file" name="fileH" hidden accept=".xlsx,.xls">
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Drag & Drop Script -->
  <script>
    document.querySelectorAll(".drop-zone").forEach(zone => {
      const input = zone.querySelector("input");

      zone.addEventListener("click", () => input.click());

      input.addEventListener("change", () => {
        if (input.files.length) {
          zone.querySelector("p").textContent = "✅ File dipilih: " + input.files[0].name;
        }
      });

      zone.addEventListener("dragover", e => {
        e.preventDefault();
        zone.classList.add("dragover");
      });

      ["dragleave", "dragend"].forEach(type => {
        zone.addEventListener(type, () => zone.classList.remove("dragover"));
      });

      zone.addEventListener("drop", e => {
        e.preventDefault();
        zone.classList.remove("dragover");
        if (e.dataTransfer.files.length) {
          input.files = e.dataTransfer.files;
          zone.querySelector("p").textContent = "✅ File dipilih: " + input.files[0].name;
        }
      });
    });
  </script>
</body>
</html>
