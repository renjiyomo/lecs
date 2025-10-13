// script.js
document.addEventListener('DOMContentLoaded', () => {
  const gradeSelect = document.getElementById('grade_level_select');
  const sectionSelect = document.getElementById('section_select');
  const modeToggle = document.getElementById('modeToggle');
  const birthInput = document.getElementById('birthdate');
  const ageField = document.getElementById('ageField');

  // Mode toggle: store preference
  modeToggle.addEventListener('change', e => {
    if (e.target.checked) {
      document.body.classList.remove('dark-mode');
      document.body.classList.add('light-mode');
    } else {
      document.body.classList.remove('light-mode');
      document.body.classList.add('dark-mode');
    }
  });

  // fetch sections when grade changes
  if (gradeSelect) {
    gradeSelect.addEventListener('change', function() {
      const gradeId = this.value;
      sectionSelect.innerHTML = '<option>Loading...</option>';
      fetch('get_sections.php?grade_level_id=' + encodeURIComponent(gradeId))
        .then(resp => resp.json())
        .then(data => {
          sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
          data.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.section_id;
            opt.textContent = s.section_name;
            sectionSelect.appendChild(opt);
          });
        }).catch(err => {
          sectionSelect.innerHTML = '<option value="">-- Error loading --</option>';
          console.error(err);
        });
    });
  }

  // compute age automatically
  if (birthInput && ageField) {
    birthInput.addEventListener('change', function() {
      const b = this.value;
      if (!b) return;
      const dob = new Date(b);
      const diff = Date.now() - dob.getTime();
      const age = Math.floor(diff / (1000 * 60 * 60 * 24 * 365.25));
      ageField.value = age;
    });
  }

  // Export SF1 button
  const exportBtn = document.getElementById('exportBtn');
  if (exportBtn) exportBtn.addEventListener('click', () => {
    window.location = 'export_sf1.php';
  });

  // Import button opens import page
  const importBtn = document.getElementById('importBtn');
  if (importBtn) importBtn.addEventListener('click', () => {
    window.location = 'import_sf1.php';
  });
});
