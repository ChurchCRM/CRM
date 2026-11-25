export function downloadBackupFile(filename) {
  const form = document.getElementById("backup-download-form");
  form.action = `${window.CRM.root}/admin/api/database/download/${encodeURIComponent(filename)}`;
  form.submit();
}
