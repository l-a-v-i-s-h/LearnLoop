document.addEventListener('DOMContentLoaded', () => {
    const addBtn = document.getElementById('addMemberBtn');
    const inviteCard = document.getElementById('inviteCard');
    const cancelBtn = document.getElementById('cancelInvite');
    const confirmBtn = document.getElementById('confirmInvite');
    const toast = document.getElementById('inviteToast');
    const emailInput = document.getElementById('inviteEmail');

    addBtn.addEventListener('click', () => {
        inviteCard.style.display = 'block';
    });

    cancelBtn.addEventListener('click', () => {
        inviteCard.style.display = 'none';
        emailInput.value = '';
    });

    confirmBtn.addEventListener('click', () => {
        if (emailInput.value.trim() !== "") {
            inviteCard.style.display = 'none';
            emailInput.value = '';
            
            toast.style.display = 'block';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        } else {
            alert("Please enter an email address.");
        }
    });
});