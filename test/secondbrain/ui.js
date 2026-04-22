// Tiny interactive hook (optional)
document.addEventListener('DOMContentLoaded', () => {
  const search = document.querySelector('.search-wrap input');
  if (!search) return;
  search.addEventListener('input', () => {
    // Simple placeholder interactivity: no real filtering in this static mock
  });
});
