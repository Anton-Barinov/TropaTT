(() => {
  'use strict';
  const $ = id => document.getElementById(id);
  const api = (path, options = {}) => window.CRM?.api?.request ? window.CRM.api.request(path, options).then(x => x.data || x) : Promise.reject(new Error('CRM API client unavailable'));
  let sessionId = '';
  const message = (text, error = false) => { const el = $('hubmMessage'); el.textContent = text; el.className = 'small ' + (error ? 'hubm-state-error' : 'text-success'); };
  const mapping = () => ({firstname:$('mapFirstname').value.trim(),lastname:$('mapLastname').value.trim(),email:$('mapEmail').value.trim(),phone:$('mapPhone').value.trim(),notes:$('mapNotes').value.trim()});
  const paint = report => {
    $('hubmCounts').replaceChildren(...Object.entries(report.counts || {}).map(([key,value]) => { const el=document.createElement('span'); el.className='hubm-count'; el.textContent=`${key}: ${value}`; return el; }));
    const rows = (report.items || []).map(item => { const tr=document.createElement('tr'); for (const val of [item.hubspot_id, item.contact?.title || '', item.status === 'pending' ? (item.already_imported ? 'Уже импортирован' : 'Готов к импорту') : item.status]) { const td=document.createElement('td'); td.textContent=String(val); tr.append(td); } return tr; });
    if (!rows.length) { const tr=document.createElement('tr'); const td=document.createElement('td'); td.colSpan=3; td.textContent='В сессии пока нет записей.'; tr.append(td); rows.push(tr); }
    $('hubmRows').replaceChildren(...rows);
    $('hubmImport').disabled = !sessionId || !(report.counts?.ready > 0);
    $('hubmRetry').disabled = !sessionId || !(report.counts?.failed > 0);
    $('hubmRollback').disabled = !sessionId || !(report.counts?.imported_this_session > 0);
    $('hubmDiscard').disabled = !sessionId || !(report.counts?.ready > 0 || report.counts?.already_imported > 0 || report.counts?.failed > 0);
  };
  document.addEventListener('DOMContentLoaded', () => {
    $('hubmCreateSession')?.addEventListener('click', async () => { try { const r=await api('/api/v1/_module/crm.hubspot-migration/sessions',{method:'POST',body:{}}); sessionId=r.session_id; sessionStorage.setItem('hubmSessionId',sessionId); $('hubmSessionLabel').textContent=`Сессия: ${sessionId}`; $('hubmPreview').disabled=false; message('Сессия создана. Вставьте JSON и подготовьте предпросмотр.'); } catch(e) { message(e.message || 'Не удалось создать сессию.',true); } });
    $('hubmPreview')?.addEventListener('click', async () => { try { if (!sessionId) throw new Error('Сначала создайте сессию.'); const records=JSON.parse($('hubmJson').value); if (!Array.isArray(records)) throw new Error('Корневое значение JSON должно быть массивом.'); const r=await api('/api/v1/_module/crm.hubspot-migration/sessions/contacts',{method:'POST',body:{session_id:sessionId,records,mapping:mapping()}}); paint(r); message('Предпросмотр готов. CRM пока не изменена.'); } catch(e) { message(e.message || 'Не удалось подготовить предпросмотр.',true); } });
    $('hubmImport')?.addEventListener('click', async () => { try { const r=await api(`/api/v1/_module/crm.hubspot-migration/sessions/${encodeURIComponent(sessionId)}/import`,{method:'POST',body:{limit:25}}); paint(r.report); message(`Пакет обработан: импортировано ${r.imported}, пропущено ${r.skipped}, ошибок ${r.errors}.`); } catch(e) { message(e.message || 'Импорт не выполнен.',true); } });
    $('hubmRetry')?.addEventListener('click', async () => { try { const r=await api(`/api/v1/_module/crm.hubspot-migration/sessions/${encodeURIComponent(sessionId)}/retry-errors`,{method:'POST',body:{}}); paint(r.report); message(`Повтор обработал пакет: импортировано ${r.imported}, пропущено ${r.skipped}, ошибок ${r.errors}.`); } catch(e) { message(e.message || 'Повтор не выполнен.',true); } });
    $('hubmRollback')?.addEventListener('click', async () => { if (!window.confirm('Удалить созданные этой сессией контакты, если после импорта их не меняли? Изменённые записи останутся.')) return; try { const r=await api(`/api/v1/_module/crm.hubspot-migration/sessions/${encodeURIComponent(sessionId)}/rollback`,{method:'POST',body:{}}); paint(r.report); message(`Откат: удалено ${r.deleted_unchanged_records}; оставлено изменённых или недоступных ${r.retained_changed_or_unavailable_records}.`); } catch(e) { message(e.message || 'Откат не выполнен.',true); } });
    $('hubmDiscard')?.addEventListener('click', async () => { if (!window.confirm('Очистить ожидающие импорта данные и ошибки этой сессии? Уже импортированные контакты останутся.')) return; try { const r=await api(`/api/v1/_module/crm.hubspot-migration/sessions/${encodeURIComponent(sessionId)}/discard-pending`,{method:'POST',body:{}}); paint(r.report); message(`Очищено записей: ${r.discarded_staged_records}. Уже импортированные записи сохранены.`); } catch(e) { message(e.message || 'Не удалось очистить данные сессии.',true); } });
    const savedSession=sessionStorage.getItem('hubmSessionId');
    if (savedSession && /^hsm_[a-f0-9]{32}$/.test(savedSession)) { sessionId=savedSession; $('hubmSessionLabel').textContent=`Сессия: ${sessionId}`; $('hubmPreview').disabled=false; api(`/api/v1/_module/crm.hubspot-migration/sessions/${encodeURIComponent(sessionId)}`).then(report => { paint(report); message('Сохранённая сессия восстановлена.'); }).catch(() => { sessionStorage.removeItem('hubmSessionId'); sessionId=''; $('hubmSessionLabel').textContent='Сессия не выбрана'; }); }
  });
})();
