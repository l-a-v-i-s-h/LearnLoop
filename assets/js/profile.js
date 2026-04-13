document.addEventListener('DOMContentLoaded', () => {
    const messages = document.querySelectorAll('.profile-message');

    if (messages.length === 0) {
        return;
    }

    setTimeout(() => {
        messages.forEach((message) => {
            message.style.display = 'none';
        });
    }, 3000);
});