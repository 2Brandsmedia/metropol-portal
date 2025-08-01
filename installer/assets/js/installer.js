/**
 * Metropol Portal Installer - JavaScript
 */

// Auto-focus first input field
document.addEventListener('DOMContentLoaded', function() {
    const firstInput = document.querySelector('input[type="text"]:not([readonly]), input[type="email"]:not([readonly]), input[type="password"]:not([readonly])');
    if (firstInput) {
        firstInput.focus();
    }
});

// Form validation helpers
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

function validatePassword(password) {
    // At least 8 characters, one uppercase, one lowercase, one number
    const re = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
    return re.test(password);
}

// Database connection test (AJAX)
function testDatabaseConnection() {
    const resultSpan = document.getElementById('test-result');
    const button = event.target;
    
    // Collect form data
    const formData = new FormData();
    formData.append('action', 'test_db');
    formData.append('host', document.getElementById('db_host').value);
    formData.append('port', document.getElementById('db_port').value);
    formData.append('name', document.getElementById('db_name').value);
    formData.append('user', document.getElementById('db_user').value);
    formData.append('pass', document.getElementById('db_pass').value);
    
    // Show loading
    button.disabled = true;
    resultSpan.textContent = 'Testing...';
    resultSpan.className = 'ml-3 text-sm text-gray-600';
    
    // Make AJAX request
    fetch('install.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultSpan.textContent = data.message || 'Connection successful!';
            resultSpan.className = 'ml-3 text-sm text-green-600 font-medium';
        } else {
            resultSpan.textContent = data.message || 'Connection failed!';
            resultSpan.className = 'ml-3 text-sm text-red-600 font-medium';
        }
    })
    .catch(error => {
        resultSpan.textContent = 'Error: ' + error.message;
        resultSpan.className = 'ml-3 text-sm text-red-600 font-medium';
    })
    .finally(() => {
        button.disabled = false;
    });
}

// Progress tracking
let installationInProgress = false;

function updateProgress(percent, message) {
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    
    if (progressBar) {
        progressBar.style.width = percent + '%';
    }
    
    if (progressText) {
        progressText.textContent = message;
    }
}

// Prevent accidental navigation during installation
window.addEventListener('beforeunload', function(e) {
    if (installationInProgress) {
        e.preventDefault();
        e.returnValue = 'Installation is in progress. Are you sure you want to leave?';
    }
});

// Smooth scroll to errors
function scrollToError() {
    const errorElement = document.querySelector('.bg-red-50');
    if (errorElement) {
        errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

// Auto-scroll to errors on page load
if (document.querySelector('.bg-red-50')) {
    setTimeout(scrollToError, 100);
}

// Copy to clipboard functionality
function copyToClipboard(text) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() {
            showToast('Copied to clipboard!');
        }, function() {
            fallbackCopyToClipboard(text);
        });
    } else {
        fallbackCopyToClipboard(text);
    }
}

function fallbackCopyToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.top = "0";
    textArea.style.left = "0";
    textArea.style.opacity = "0";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showToast('Copied to clipboard!');
    } catch (err) {
        showToast('Failed to copy', 'error');
    }
    
    document.body.removeChild(textArea);
}

// Toast notifications
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `fixed bottom-4 right-4 px-6 py-3 rounded-lg text-white font-medium shadow-lg transform transition-all duration-300 translate-y-full ${
        type === 'success' ? 'bg-green-600' : 'bg-red-600'
    }`;
    toast.textContent = message;
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.classList.remove('translate-y-full');
    }, 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.classList.add('translate-y-full');
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}

// Installation countdown
function startInstallationCountdown() {
    let seconds = 5;
    const countdownElement = document.getElementById('countdown');
    
    const interval = setInterval(() => {
        seconds--;
        if (countdownElement) {
            countdownElement.textContent = seconds;
        }
        
        if (seconds <= 0) {
            clearInterval(interval);
            window.location.href = 'public/index.php';
        }
    }, 1000);
}