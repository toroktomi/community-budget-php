document.addEventListener('DOMContentLoaded', function() {
    const voteButtons = document.querySelectorAll('.vote-btn');

    voteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const projectId = this.getAttribute('data-project-id');
            const action = this.getAttribute('data-action');

            this.disabled = true;
            const originalText = this.textContent;
            this.textContent = 'Folyamatban...';

            fetch('api-vote.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    project_id: projectId,
                    action: action
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Hiba: ' + data.error);
                    this.disabled = false;
                    this.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Hiba történt a szavazás során.');
                this.disabled = false;
                this.textContent = originalText;
            });
        });
    });
});
