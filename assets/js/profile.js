document.addEventListener('DOMContentLoaded', () => {
    const profileForm = document.getElementById('profileForm');
    const passwordForm = document.getElementById('passwordForm');
    const toast = document.getElementById('successToast');

    const showToast = (e) => {
        e.preventDefault();
        toast.style.display = 'block';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3000);
    };

    if (profileForm) profileForm.addEventListener('submit', showToast);
    if (passwordForm) passwordForm.addEventListener('submit', showToast);
});``