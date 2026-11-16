import { Controller } from '@hotwired/stimulus';

// Moniteur par groupe : glisser-déposer d'un moniteur (ligne du tableau ou pastille d'un groupe) vers un groupe ou vers le tableau.
//  - tableau -> groupe : ajout au groupe
//  - groupe -> groupe  : déplacement
//  - groupe -> tableau : retrait du groupe
// Chaque dépôt est enregistré aussitôt, puis la page est rechargée pour refléter les listes.
export default class extends Controller {
    static targets = ['statut'];
    static values = { url: String, token: String };

    debut(event) {
        this.glisse = { moniteur: event.currentTarget.dataset.moniteurId, source: event.currentTarget.dataset.sourceId || '' };
        event.dataTransfer.setData('text/plain', this.glisse.moniteur);
        event.dataTransfer.effectAllowed = 'move';
        event.currentTarget.classList.add('is-dragging');
        event.stopPropagation();
    }

    fin(event) {
        event.currentTarget.classList.remove('is-dragging');
        this.glisse = null;
        this.element.querySelectorAll('.drop-cible').forEach((e) => e.classList.remove('drop-cible'));
    }

    survol(event) {
        if (!this.glisse) {
            return;
        }
        const cible = event.currentTarget;
        // Le tableau n'accepte que les moniteurs venant d'un groupe (pour les retirer)
        if (!cible.dataset.groupeId && !this.glisse.source) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        cible.classList.add('drop-cible');
    }

    quitte(event) {
        event.currentTarget.classList.remove('drop-cible');
    }

    async depotGroupe(event) {
        event.preventDefault();
        event.currentTarget.classList.remove('drop-cible');
        if (!this.glisse) {
            return;
        }
        const cible = event.currentTarget.dataset.groupeId;
        if (cible === this.glisse.source) {
            return;
        }
        await this.enregistrer({ moniteur: this.glisse.moniteur, cible, source: this.glisse.source });
    }

    async depotTableau(event) {
        event.preventDefault();
        event.currentTarget.classList.remove('drop-cible');
        if (!this.glisse || !this.glisse.source) {
            return;
        }
        await this.enregistrer({ moniteur: this.glisse.moniteur, cible: '', source: this.glisse.source });
    }

    async enregistrer(donnees) {
        this.afficherStatut('Enregistrement…');
        try {
            const reponse = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ ...donnees, _token: this.tokenValue }),
            });
            const resultat = await reponse.json();
            if (!reponse.ok || !resultat.ok) {
                throw new Error(resultat.message || 'Enregistrement impossible.');
            }
            window.location.reload();
        } catch (e) {
            this.afficherStatut(e.message, true);
        }
    }

    afficherStatut(message, erreur = false) {
        this.statutTarget.textContent = message;
        this.statutTarget.classList.toggle('erreur', erreur);
        this.statutTarget.hidden = false;
    }
}
