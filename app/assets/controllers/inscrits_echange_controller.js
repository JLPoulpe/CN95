import { Controller } from '@hotwired/stimulus';

// Fiche des inscrits : glisser un membre de la liste d'attente sur la liste principale pour le favoriser.
//  - sur un inscrit du même groupe (moniteurs / plongeurs) : échange des places
//  - sur la liste principale (hors ligne) : promotion, si une place est libre
// Le serveur valide, puis la page est rechargée.
export default class extends Controller {
    static targets = ['statut'];
    static values = { url: String, token: String };

    debut(event) {
        const ligne = event.target.closest('tr[data-reponse-id]');
        this.glisse = ligne ? { reponse: ligne.dataset.reponseId, groupe: ligne.dataset.groupe } : null;
    }

    fin() {
        this.glisse = null;
        this.element.querySelectorAll('.drop-cible').forEach((e) => e.classList.remove('drop-cible'));
    }

    survol(event) {
        const zone = event.currentTarget;
        if (!this.glisse || zone.dataset.groupe !== this.glisse.groupe) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';
        this.element.querySelectorAll('.drop-cible').forEach((e) => e.classList.remove('drop-cible'));
        const ligne = event.target.closest('tr[data-inscrit-id]');
        (ligne || zone).classList.add('drop-cible');
    }

    quitte(event) {
        if (!event.currentTarget.contains(event.relatedTarget)) {
            event.currentTarget.querySelectorAll('.drop-cible').forEach((e) => e.classList.remove('drop-cible'));
            event.currentTarget.classList.remove('drop-cible');
        }
    }

    async depot(event) {
        const zone = event.currentTarget;
        if (!this.glisse || zone.dataset.groupe !== this.glisse.groupe) {
            return;
        }
        event.preventDefault();
        const ligne = event.target.closest('tr[data-inscrit-id]');
        const donnees = { reponse: this.glisse.reponse, remplace: ligne ? ligne.dataset.inscritId : '' };
        this.fin();
        await this.envoyer(donnees);
    }

    async envoyer(donnees) {
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
