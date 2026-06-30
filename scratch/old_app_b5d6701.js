// assets/js/app.js

// Register Service Worker for PWA
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/vhv/service-worker.js')
            .then(reg => console.log('Service Worker Registered successfully.', reg.scope))
            .catch(err => console.error('Service Worker registration failed:', err));
    });
}

// Geo-location Helper with Promise
function getCurrentLocation() {
    return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
            reject(new Error('Geolocation is not supported by your browser.'));
            return;
        }

        const options = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
        };

        navigator.geolocation.getCurrentPosition(
            position => {
                resolve({
                    lat: position.coords.latitude,
                    lng: position.coords.longitude,
                    accuracy: position.coords.accuracy
                });
            },
            error => {
                reject(error);
            },
            options
        );
    });
}

// Offline/Online Status Monitor
window.addEventListener('online', updateOnlineStatus);
window.addEventListener('offline', updateOnlineStatus);

function updateOnlineStatus() {
    const isOnline = navigator.onLine;
    let statusBanner = document.getElementById('offline-banner');
    
    if (!isOnline) {
        if (!statusBanner) {
            statusBanner = document.createElement('div');
            statusBanner.id = 'offline-banner';
            statusBanner.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                background-color: #ef4444;
                color: white;
                text-align: center;
                padding: 10px;
                font-weight: bold;
                z-index: 9999;
                font-size: 16px;
            `;
            statusBanner.innerHTML = '⚠️ คุณกำลังใช้งานโหมดออฟไลน์ - ข้อมูลจะถูกบันทึกเมื่อเชื่อมต่ออินเทอร์เน็ต';
            document.body.prepend(statusBanner);
        }
    } else {
        if (statusBanner) {
            statusBanner.remove();
        }
    }
}

// Zero-Typing Numeric Pad Helper
class VhvNumPad {
    constructor(inputId, padContainerId, displayBoxId = null) {
        this.input = document.getElementById(inputId);
        this.container = document.getElementById(padContainerId);
        this.displayBox = displayBoxId ? document.getElementById(displayBoxId) : null;
        this.currentValue = '';
        if (this.input && this.container) {
            this.init();
        }
    }

    init() {
        this.container.innerHTML = `
            <div class="numpad-grid">
                <button type="button" class="numpad-btn" data-val="1">1</button>
                <button type="button" class="numpad-btn" data-val="2">2</button>
                <button type="button" class="numpad-btn" data-val="3">3</button>
                <button type="button" class="numpad-btn" data-val="4">4</button>
                <button type="button" class="numpad-btn" data-val="5">5</button>
                <button type="button" class="numpad-btn" data-val="6">6</button>
                <button type="button" class="numpad-btn" data-val="7">7</button>
                <button type="button" class="numpad-btn" data-val="8">8</button>
                <button type="button" class="numpad-btn" data-val="9">9</button>
                <button type="button" class="numpad-btn btn-action" data-val=".">.</button>
                <button type="button" class="numpad-btn" data-val="0">0</button>
                <button type="button" class="numpad-btn btn-action" data-val="del">⌫</button>
            </div>
        `;

        this.container.querySelectorAll('.numpad-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const val = btn.getAttribute('data-val');
                this.handlePress(val);
            });
        });
    }

    handlePress(val) {
        if (val === 'del') {
            this.currentValue = this.currentValue.slice(0, -1);
        } else if (val === '.') {
            if (!this.currentValue.includes('.')) {
                this.currentValue += '.';
            }
        } else {
            // limit to length
            if (this.currentValue.length < 6) {
                this.currentValue += val;
            }
        }
        this.updateDisplay();
    }

    setValue(val) {
        this.currentValue = val.toString();
        this.updateDisplay();
    }
    
    updateDisplay() {
        this.input.value = this.currentValue;
        if (this.displayBox) {
            this.displayBox.innerText = this.currentValue || '0';
        }
        // Trigger input event programmatically
        const event = new Event('input', { bubbles: true });
        this.input.dispatchEvent(event);
    }
}
