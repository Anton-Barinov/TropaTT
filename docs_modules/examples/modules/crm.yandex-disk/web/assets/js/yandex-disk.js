(() => {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const status = document.getElementById('yadiskStatusMeta');
    if (status) status.textContent = 'Интеграция не настроена; обмен файлами недоступен.';

    const message = document.getElementById('yadiskMessage');
    if (message) {
      message.textContent = 'Настройки подключения и загрузка файлов пока не реализованы.';
      message.className = 'small mt-3 text-muted';
    }
  });
})();
