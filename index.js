// Функция проверки состояния БД
async function checkDatabaseStatus() {
    try {
        const response = await fetch('db_status.php');
        const status = await response.json();
        
        if (!status.database_connected) {
            console.error('Database connection error:', status.database_error);
            showDatabaseWarning();
        }
        
        return status;
    } catch (error) {
        console.error('Failed to check database status:', error);
        return null;
    }
}

// Показать предупреждение пользователю
function showDatabaseWarning() {
    const warning = document.createElement('div');
    warning.style.cssText = `
        position: fixed;
        top: 10px;
        right: 10px;
        background: #ff4444;
        color: white;
        padding: 10px;
        border-radius: 5px;
        z-index: 10000;
        max-width: 300px;
        font-size: 14px;
    `;
    warning.innerHTML = `
        <strong>Внимание!</strong><br>
        Возникли временные проблемы с записью.<br>
        Пожалуйста, позвоните нам для записи.
    `;
    document.body.appendChild(warning);
    
    // Автоматически скрыть через 10 секунд
    setTimeout(() => {
        warning.remove();
    }, 10000);
}

// Проверка при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    // Проверяем статус БД (можно делать реже в продакшене)
    checkDatabaseStatus();
    
    // Также проверяем при отправке формы
    const bookingForm = document.getElementById('booking-form');
    if (bookingForm) {
        bookingForm.addEventListener('submit', async function(e) {
            const status = await checkDatabaseStatus();
            if (!status || !status.database_connected) {
                e.preventDefault();
                alert('В настоящее время запись через сайт временно недоступна. Пожалуйста, позвоните нам для записи.');
                return false;
            }
        });
    }
});

// Периодическая проверка (каждые 5 минут)
setInterval(checkDatabaseStatus, 300000);

// Проверка доступности API при загрузке
async function testAPI() {
    try {
        const response = await fetch('busy_dates.php');
        if (!response.ok) throw new Error('API not responding');
        
        const data = await response.json();
        console.log('API is working, busy dates:', data.busy_dates);
        return true;
    } catch (error) {
        console.error('API test failed:', error);
        return false;
    }
}

// Запуск проверки
testAPI().then(success => {
    if (!success) {
        console.warn('API недоступен, некоторые функции могут не работать');
    }
});

// Улучшенная функция для получения занятых дат
async function fetchBusyDates() {
    try {
        const response = await fetch('busy_dates.php');
        if (!response.ok) throw new Error('Network error');
        
        const data = await response.json();
        return data.busy_dates || [];
    } catch (error) {
        console.warn('Ошибка загрузки занятых дат:', error);
        return [];
    }
}

// Инициализация Flatpickr с обработкой ошибок
function initDatePicker() {
    const bookingDateInput = document.getElementById('booking-date');
    if (!bookingDateInput) return;
    
    fetchBusyDates().then(busyDates => {
        try {
            flatpickr(bookingDateInput, {
                enableTime: true,
                dateFormat: "Y-m-d H:i",
                minDate: "today",
                time_24hr: true,
                locale: "ru",
                minTime: "09:00",
                maxTime: "21:00",
                disable: [
                    function(date) {
                        return date.getDay() === 0; // Воскресенье
                    },
                    ...busyDates
                ]
            });
        } catch (error) {
            console.error('Ошибка инициализации календаря:', error);
            // Резервный вариант - обычный input
            bookingDateInput.type = 'datetime-local';
        }
    });
}

// Запуск при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    initDatePicker();
});


