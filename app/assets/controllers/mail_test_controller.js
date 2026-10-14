import { Controller } from '@hotwired/stimulus';

/*
 * Envoie un mail de test d'invitation avec les valeurs du formulaire en cours de saisie, sans rien enregistrer.
 */
export default class extends Controller {
    static targets = ['dialog', 'email', 'feedback', 'submit'];
    static values = { url: String, token: String };

    ouvrir() {
        this.feedbackTarget.hidden = true;
        this.dialogTarget.showModal();
    }

    fermer() {
        this.dialogTarget.close();
    }

    async envoyer(event) {
        event.preventDefault();
        this.submitTarget.disabled = true;

        const donnees = new FormData(this.element.querySelector('form'));
        donnees.set('_token', this.tokenValue);
        donnees.set('email', this.emailTarget.value);

        try {
            const reponse = await fetch(this.urlValue, {
                method: 'POST',
                body: donnees,
                headers: { Accept: 'application/json' },
            });
            const resultat = await reponse.json();
            this.afficher(resultat.succes, resultat.message);
        } catch (erreur) {
            this.afficher(false, 'Le serveur n\'a pas répondu. Réessayez.');
        } finally {
            this.submitTarget.disabled = false;
        }
    }

    afficher(succes, message) {
        this.feedbackTarget.textContent = message;
        this.feedbackTarget.className = `alert ${succes ? 'alert-success' : 'alert-danger'}`;
        this.feedbackTarget.hidden = false;
    }
}
