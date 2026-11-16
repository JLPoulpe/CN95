import { Controller } from '@hotwired/stimulus';

// Fiche des inscrits : un clic sur l'encoche ou la croix du CACI ouvre une petite fenêtre avec la date de fin de validité.
export default class extends Controller {
    static targets = ['dialogue', 'nom', 'fin'];

    afficher(event) {
        const { nom, fin } = event.currentTarget.dataset;
        this.nomTarget.textContent = nom;
        this.finTarget.textContent = fin || 'Non renseignée';
        this.dialogueTarget.showModal();
    }
}
