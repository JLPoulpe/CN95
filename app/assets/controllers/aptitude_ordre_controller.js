import { Controller } from '@hotwired/stimulus';

// Liste des aptitudes : glisser-déposer natif d'une ligne pour la réordonner.
// Dès que la ligne est lâchée et que l'ordre a changé, le nouvel ordre est enregistré aussitôt (sauvegarde automatique).
export default class extends Controller {
    static targets = ['corps', 'statut'];
    static values = { url: String, token: String };

    connect() {
        this.ordreInitial = null;
        this.glissee = null;
    }

    debut(event) {
        this.glissee = event.currentTarget;
        this.ordreInitial = this.ids();
        event.dataTransfer.setData('text/plain', this.glissee.dataset.aptitudeId);
        event.dataTransfer.effectAllowed = 'move';
        this.glissee.classList.add('is-dragging');
    }

    survol(event) {
        if (!this.glissee || this.glissee === event.currentTarget) {
            return;
        }
        event.preventDefault();
        event.dataTransfer.dropEffect = 'move';

        // Insère la ligne glissée avant ou après la ligne survolée selon la moitié de la ligne pointée
        const cible = event.currentTarget;
        const milieu = cible.getBoundingClientRect().top + cible.offsetHeight / 2;
        if (event.clientY < milieu) {
            cible.before(this.glissee);
        } else {
            cible.after(this.glissee);
        }
    }

    depot(event) {
        event.preventDefault();
    }

    async fin() {
        if (!this.glissee) {
            return;
        }
        this.glissee.classList.remove('is-dragging');
        this.glissee = null;

        const nouvelOrdre = this.ids();
        if (JSON.stringify(nouvelOrdre) === JSON.stringify(this.ordreInitial)) {
            return;
        }
        await this.enregistrer(nouvelOrdre);
    }

    async enregistrer(ids) {
        this.afficherStatut('Enregistrement…');
        try {
            const reponse = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({ ids, _token: this.tokenValue }),
            });
            const donnees = await reponse.json();
            if (!reponse.ok || !donnees.ok) {
                throw new Error(donnees.message || 'Enregistrement impossible.');
            }
            this.mettreAJourOrdres();
            this.afficherStatut('Ordre enregistré.');
        } catch (e) {
            this.afficherStatut(e.message + ' Rechargement de la page…', true);
            window.setTimeout(() => window.location.reload(), 1500);
        }
    }

    // Renumérote la colonne « Ordre » selon la position des lignes
    mettreAJourOrdres() {
        this.corpsTarget.querySelectorAll('tr[data-aptitude-id]').forEach((ligne, index) => {
            const cellule = ligne.querySelector('[data-ordre-cellule]');
            if (cellule) {
                cellule.textContent = index + 1;
            }
        });
    }

    ids() {
        return Array.from(this.corpsTarget.querySelectorAll('tr[data-aptitude-id]'), (ligne) => Number(ligne.dataset.aptitudeId));
    }

    afficherStatut(message, erreur = false) {
        this.statutTarget.textContent = message;
        this.statutTarget.classList.toggle('erreur', erreur);
        this.statutTarget.hidden = false;
    }
}
