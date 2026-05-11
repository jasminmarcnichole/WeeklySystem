(function () {
    const app = document.getElementById('dashboard-app');
    if (!app) {
        return;
    }

    const csrfToken = app.dataset.csrf;
    const boardEl = app.querySelector('[data-board]');
    const daysEl = app.querySelector('[data-days]');
    const notificationsEl = app.querySelector('[data-notifications]');
    const summaryEl = document.getElementById('summary');
    const updatedEl = app.querySelector('[data-updated]');
    const toastEl = document.querySelector('[data-toast]');

    let currentSnapshot = null;
    let toastTimer = null;

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function safeColor(value) {
        return /^#[0-9a-f]{3,8}$/i.test(value || '') ? value : '#355c7d';
    }

    function showToast(message, isError) {
        if (!toastEl) {
            return;
        }

        toastEl.textContent = message;
        toastEl.classList.toggle('error', Boolean(isError));
        toastEl.hidden = false;
        window.clearTimeout(toastTimer);
        toastTimer = window.setTimeout(() => {
            toastEl.hidden = true;
        }, 3600);
    }

    async function parseResponse(response) {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (error) {
            throw new Error('The server returned an unexpected response.');
        }
    }

    async function request(action, data) {
        const body = new URLSearchParams();
        body.set('action', action);
        body.set('csrf_token', csrfToken);

        Object.entries(data || {}).forEach(([key, value]) => {
            body.set(key, value);
        });

        const response = await fetch('api.php', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body,
        });
        const payload = await parseResponse(response);

        if (!response.ok || !payload.ok) {
            throw new Error(payload.message || 'The board could not be updated.');
        }

        if (payload.snapshot) {
            render(payload.snapshot);
        }

        if (payload.message) {
            showToast(payload.message, false);
        }

        return payload;
    }

    async function loadSnapshot(silent) {
        try {
            const response = await fetch('api.php?action=snapshot', {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            const payload = await parseResponse(response);

            if (!response.ok || !payload.ok) {
                throw new Error(payload.message || 'Unable to sync the board.');
            }

            render(payload.snapshot);
        } catch (error) {
            if (!silent) {
                showToast(error.message, true);
            }
        }
    }

    function render(snapshot) {
        currentSnapshot = snapshot;
        renderSummary(snapshot.summary);
        renderDays(snapshot.days);
        renderTasks(snapshot.tasks, snapshot.days, snapshot.planning_open);
        if (notificationsEl) {
            renderNotifications(snapshot.notifications);
        }

        if (updatedEl) {
            const time = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            updatedEl.textContent = 'Updated ' + time;
        }
    }

    function renderSummary(summary) {
        if (!summaryEl) {
            return;
        }

        summaryEl.innerHTML = [
            ['Total', summary.total],
            ['In progress', summary.in_progress],
            ['Completed', summary.completed],
            ['Week progress', summary.progress + '%'],
        ].map(([label, value]) => `
            <article class="metric-card">
                <span>${escapeHtml(label)}</span>
                <strong>${escapeHtml(value)}</strong>
            </article>
        `).join('');
    }

    function renderDays(days) {
        if (!daysEl) {
            return;
        }
        daysEl.innerHTML = `
            <div class="board-axis">
                <strong>Task / Step</strong>
                <span>Details and day bars</span>
            </div>
            ${days.map((day) => `
                <div class="board-day ${day.is_today ? 'is-today' : ''}">
                    <strong>${escapeHtml(day.name)}</strong>
                    <span>${escapeHtml(day.label)}</span>
                </div>
            `).join('')}
        `;
    }

    function renderTasks(tasks, days, planningOpen) {
        if (!boardEl) {
            return;
        }
        if (!tasks.length) {
            boardEl.innerHTML = `
                <div class="empty-state">
                    The weekly board is clear. Add a task during the Sunday/Monday planning window.
                </div>
            `;
            return;
        }

        boardEl.innerHTML = tasks.map((task) => renderTask(task, days, planningOpen)).join('');
    }

    function renderTask(task, days, planningOpen) {
        const canStart = task.status === 'pending';
        const canComplete = task.status !== 'completed' && task.status !== 'failed';
        const categoryColor = safeColor(task.category_color);
        const description = task.description ? `<p class="task-description">${escapeHtml(task.description)}</p>` : '';
        const stepRows = task.steps.length
            ? task.steps.map((step) => renderStep(step, days)).join('')
            : `<div class="empty-state grid-span">No steps yet. Split this task while planning is open.</div>`;
        const stepForm = planningOpen && task.status !== 'completed' && task.status !== 'failed'
            ? renderStepComposer(task)
            : '';

        return `
            <article class="task-card ${escapeHtml(task.status)}" data-task-id="${task.id}">
                <div class="task-top">
                    <div>
                        <div class="task-title-row">
                            <h3>${escapeHtml(task.title)}</h3>
                            <span class="category-chip" style="background:${categoryColor}">${escapeHtml(task.category_name || 'Uncategorized')}</span>
                            <span class="status-chip ${escapeHtml(task.status)}">${escapeHtml(task.status_label)}</span>
                        </div>
                        ${description}
                        <div class="progress-track" aria-label="Task progress">
                            <div class="progress-fill" style="width:${Number(task.progress) || 0}%"></div>
                        </div>
                    </div>
                    <div class="task-actions">
                        <button class="button button-ghost button-small js-action" data-action="start_task" data-task-id="${task.id}" ${canStart ? '' : 'disabled'}>Start</button>
                        <button class="button button-dark button-small js-action" data-action="complete_task" data-task-id="${task.id}" ${canComplete ? '' : 'disabled'}>Finish</button>
                    </div>
                </div>
                <div class="gantt-scroll">
                    <div class="gantt-grid">
                        ${stepRows}
                        ${stepForm}
                    </div>
                </div>
            </article>
        `;
    }

    function renderStep(step, days) {
        const canStart = step.status === 'pending';
        const canComplete = step.status !== 'completed' && step.status !== 'failed';
        const detail = step.step_description ? `<small>${escapeHtml(step.step_description)}</small>` : '';

        return `
            <div class="step-info">
                <div>
                    <strong>${escapeHtml(step.step_title)}</strong>
                    ${detail}
                    <small>${escapeHtml(step.start_date)} to ${escapeHtml(step.due_date)}</small>
                </div>
                <div class="step-actions">
                    <span class="status-chip ${escapeHtml(step.status)}">${escapeHtml(step.status_label)}</span>
                    <button class="button button-ghost button-small js-action" data-action="start_step" data-step-id="${step.id}" ${canStart ? '' : 'disabled'}>Start</button>
                    <button class="button button-dark button-small js-action" data-action="complete_step" data-step-id="${step.id}" ${canComplete ? '' : 'disabled'}>Done</button>
                </div>
            </div>
            ${days.map((day) => {
                const active = day.date >= step.start_date && day.date <= step.due_date;
                return `
                    <div class="step-cell ${active ? 'is-active ' + escapeHtml(step.status) : ''} ${day.is_today ? 'is-today' : ''}"></div>
                `;
            }).join('')}
        `;
    }

    function renderStepComposer(task) {
        return `
            <form class="step-composer js-step-form">
                <input type="hidden" name="task_id" value="${task.id}">
                <input type="text" name="step_title" placeholder="Step title" required>
                <textarea name="step_description" placeholder="Step details"></textarea>
                <input type="date" name="start_date" min="${escapeHtml(task.start_date)}" max="${escapeHtml(task.due_date)}" value="${escapeHtml(task.start_date)}" required>
                <input type="date" name="due_date" min="${escapeHtml(task.start_date)}" max="${escapeHtml(task.due_date)}" value="${escapeHtml(task.due_date)}" required>
                <button class="button button-ghost button-small" type="submit">Add step</button>
            </form>
        `;
    }

    function renderNotifications(notifications) {
        if (!notificationsEl) {
            return;
        }
        if (!notifications.length) {
            notificationsEl.innerHTML = '<div class="empty-state">No Gmail notices queued yet.</div>';
            return;
        }

        notificationsEl.innerHTML = notifications.map((notice) => `
            <article class="notification-item">
                <div>
                    <strong>${escapeHtml(notice.subject)}</strong>
                    <span>${escapeHtml(notice.message)}</span>
                </div>
                <button class="notification-status js-action" data-action="read_notification" data-notification-id="${notice.id}">
                    ${escapeHtml(notice.status)}
                </button>
            </article>
        `).join('');
    }

    function formToObject(form) {
        const data = {};
        new FormData(form).forEach((value, key) => {
            data[key] = value;
        });
        return data;
    }

    document.addEventListener('submit', async (event) => {
        const taskForm = event.target.closest('.js-task-form');
        const stepForm = event.target.closest('.js-step-form');

        if (!taskForm && !stepForm) {
            return;
        }

        event.preventDefault();
        const form = taskForm || stepForm;
        const action = taskForm ? 'create_task' : 'create_step';
        const button = form.querySelector('button[type="submit"]');

        try {
            if (button) {
                button.disabled = true;
            }
            await request(action, formToObject(form));
            form.reset();

            if (taskForm && currentSnapshot) {
                const start = taskForm.querySelector('[name="start_date"]');
                const due = taskForm.querySelector('[name="due_date"]');
                if (start) {
                    start.value = currentSnapshot.week_start;
                }
                if (due) {
                    due.value = currentSnapshot.week_end;
                }
            }
        } catch (error) {
            showToast(error.message, true);
        } finally {
            if (button) {
                button.disabled = false;
            }
        }
    });

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('.js-action');
        if (!button || button.disabled) {
            return;
        }

        const action = button.dataset.action;
        const data = {};

        if (button.dataset.taskId) {
            data.task_id = button.dataset.taskId;
        }
        if (button.dataset.stepId) {
            data.step_id = button.dataset.stepId;
        }
        if (button.dataset.notificationId) {
            data.notification_id = button.dataset.notificationId;
        }

        try {
            button.disabled = true;
            await request(action, data);
        } catch (error) {
            showToast(error.message, true);
            button.disabled = false;
        }
    });

    loadSnapshot(false);
    window.setInterval(() => loadSnapshot(true), 15000);
}());
