document.addEventListener('DOMContentLoaded', () => {

    // 1. Récupération des champs du formulaire
    const inputName = document.getElementById('input-name');
    const inputBlood = document.getElementById('input-blood');
    const inputBirth = document.getElementById('input-birth');
    const inputContact = document.getElementById('input-contact');
    const inputOrgans = document.getElementById('input-organs');

    // 2. Récupération des zones d'affichage sur la carte
    const cardName = document.getElementById('card-display-name');
    const cardBlood = document.getElementById('card-display-blood');
    const cardBirth = document.getElementById('card-display-birth');
    const cardContact = document.getElementById('card-display-contact');
    const cardOrgans = document.getElementById('card-display-organs');

    // Vérification de sécurité dans la console du navigateur
    console.log("Initialisation carte.js - Vérification des éléments :");
    console.log("Nom:", { input: !!inputName, carte: !!cardName });
    console.log("Groupe Sanguin:", { input: !!inputBlood, carte: !!cardBlood });
    console.log("Naissance:", { input: !!inputBirth, carte: !!cardBirth });

    // 3. Fonction de mise à jour dynamique
    function updatePreview() {
        if (inputName && cardName) {
            const val = inputName.value.trim();
            cardName.textContent = val !== "" ? val.toUpperCase() : "Votre nom s'affichera ici";
        }

        if (inputBlood && cardBlood) {
            cardBlood.textContent = inputBlood.value !== "" ? inputBlood.value : "?";
        }

        if (inputBirth && cardBirth) {
            cardBirth.textContent = inputBirth.value !== "" ? inputBirth.value : "----";
        }

        if (inputContact && cardContact) {
            const val = inputContact.value.trim();
            cardContact.textContent = val !== "" ? val : "À compléter";
        }

        if (inputOrgans && cardOrgans) {
            const val = inputOrgans.value.trim();
            cardOrgans.textContent = val !== "" ? val : "Tous les organes et tissus (Rein, Cornée...)";
        }
    }

    // 4. Écouteurs d'événements (sur saisie et changement)
    const inputs = [inputName, inputBirth, inputContact, inputOrgans];
    inputs.forEach(input => {
        if (input) {
            input.addEventListener('input', updatePreview);
            input.addEventListener('keyup', updatePreview);
        }
    });

    if (inputBlood) {
        inputBlood.addEventListener('change', updatePreview);
    }

    // Lancer une première mise à jour immédiate au chargement
    updatePreview();

    // 5. Gestion du formulaire (Envoi AJAX)
    const form = document.getElementById('donor-form');
    if (form) {
        form.addEventListener('submit', (e) => {
            e.preventDefault();

            const formData = new FormData(form);
            const endpoint = form.getAttribute('action') || 'carte.php';

            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const text = await response.text();
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error(text || 'Réponse invalide du serveur');
                }
            })
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.message || "Une erreur est survenue.");
                }
            })
            .catch(err => {
                console.error("Erreur d'envoi:", err);
                alert("Erreur lors de l'enregistrement de la carte.");
            });
        });
    }
});
