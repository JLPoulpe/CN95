// Glisser-déposer au doigt : les navigateurs mobiles ne déclenchent pas les événements HTML5 « drag » (dragstart, dragover, drop…)
// sur les éléments draggable="true". Ce module les émet à partir des événements tactiles, pour que les contrôleurs Stimulus
// (aptitudes, liste d'attente, moniteurs par groupe, fiche de sécurité) fonctionnent à l'identique sur téléphone.
//
// Geste : appui long (~350 ms) sur l'élément, puis glisser ; un mouvement avant la fin de l'appui long reste un défilement normal.

const APPUI_LONG_MS = 350;
const TOLERANCE_PX = 10;
const MARGE_DEFILEMENT = 70;

let etat = null;

function creerEvenement(type, source, touche, extra = {}) {
    const init = {
        bubbles: true,
        cancelable: true,
        composed: true,
        clientX: touche.clientX,
        clientY: touche.clientY,
        screenX: touche.screenX,
        screenY: touche.screenY,
        dataTransfer: etat.dataTransfer,
        ...extra,
    };
    try {
        return new DragEvent(type, init);
    } catch (e) {
        const evenement = new Event(type, { bubbles: true, cancelable: true, composed: true });
        Object.assign(evenement, { clientX: init.clientX, clientY: init.clientY, dataTransfer: init.dataTransfer, relatedTarget: init.relatedTarget ?? null });
        return evenement;
    }
}

function elementSous(touche) {
    // Le fantôme est en pointer-events: none ; on cherche donc directement l'élément sous le doigt
    return document.elementFromPoint(touche.clientX, touche.clientY);
}

function creerFantome(source, touche) {
    const fantome = document.createElement('div');
    fantome.textContent = (source.innerText || source.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 60);
    Object.assign(fantome.style, {
        position: 'fixed',
        zIndex: '10000',
        pointerEvents: 'none',
        background: '#fff',
        border: '1px solid #3b82f6',
        borderRadius: '8px',
        boxShadow: '0 4px 14px rgba(0,0,0,.25)',
        padding: '8px 12px',
        font: '14px sans-serif',
        opacity: '.9',
        maxWidth: '70vw',
        transform: 'translate(-50%, -120%)',
    });
    document.body.appendChild(fantome);
    deplacerFantome(fantome, touche);

    return fantome;
}

function deplacerFantome(fantome, touche) {
    fantome.style.left = touche.clientX + 'px';
    fantome.style.top = touche.clientY + 'px';
}

function demarrer() {
    const { source, touche } = etat;
    etat.dataTransfer = new DataTransfer();
    etat.actif = true;
    etat.fantome = creerFantome(source, touche);
    if (navigator.vibrate) {
        navigator.vibrate(15);
    }
    source.dispatchEvent(creerEvenement('dragstart', source, touche));
}

function deplacer(touche) {
    deplacerFantome(etat.fantome, touche);

    // Défilement automatique près des bords de l'écran
    if (touche.clientY < MARGE_DEFILEMENT) {
        window.scrollBy(0, -12);
    } else if (touche.clientY > window.innerHeight - MARGE_DEFILEMENT) {
        window.scrollBy(0, 12);
    }

    const cible = elementSous(touche);
    if (cible !== etat.cible) {
        if (etat.cible) {
            etat.cible.dispatchEvent(creerEvenement('dragleave', etat.source, touche, { relatedTarget: cible }));
        }
        if (cible) {
            cible.dispatchEvent(creerEvenement('dragenter', etat.source, touche, { relatedTarget: etat.cible }));
        }
        etat.cible = cible;
    }
    if (cible) {
        // Comme dans un navigateur : le dépôt n'est possible que si dragover a été annulé (preventDefault)
        etat.accepte = !cible.dispatchEvent(creerEvenement('dragover', etat.source, touche));
    } else {
        etat.accepte = false;
    }
}

function terminer(touche, depot) {
    if (!etat) {
        return;
    }
    window.clearTimeout(etat.minuteur);
    if (etat.actif) {
        if (depot && etat.cible && etat.accepte) {
            etat.cible.dispatchEvent(creerEvenement('drop', etat.source, touche));
        } else if (etat.cible) {
            etat.cible.dispatchEvent(creerEvenement('dragleave', etat.source, touche));
        }
        etat.source.dispatchEvent(creerEvenement('dragend', etat.source, touche));
        etat.fantome.remove();
    }
    if (etat.draggableInitial !== null) {
        etat.source.setAttribute('draggable', etat.draggableInitial);
    }
    etat = null;
}

document.addEventListener('touchstart', (event) => {
    if (etat || event.touches.length !== 1) {
        return;
    }
    const source = event.target.closest?.('[draggable="true"]');
    if (!source) {
        return;
    }
    const touche = event.touches[0];
    // draggable est coupé le temps du geste : sinon le navigateur peut lancer son propre glisser natif en parallèle
    etat = { source, touche, depart: { x: touche.clientX, y: touche.clientY }, draggableInitial: source.getAttribute('draggable'), actif: false, cible: null, accepte: false };
    source.setAttribute('draggable', 'false');
    etat.minuteur = window.setTimeout(() => {
        if (etat) {
            demarrer();
        }
    }, APPUI_LONG_MS);
}, { passive: true });

document.addEventListener('touchmove', (event) => {
    if (!etat) {
        return;
    }
    const touche = event.touches[0];
    etat.touche = touche;
    if (!etat.actif) {
        // Mouvement avant la fin de l'appui long : c'est un défilement, on abandonne le glisser
        if (Math.hypot(touche.clientX - etat.depart.x, touche.clientY - etat.depart.y) > TOLERANCE_PX) {
            terminer(touche, false);
        }
        return;
    }
    event.preventDefault(); // bloque le défilement de la page pendant le glisser
    deplacer(touche);
}, { passive: false });

document.addEventListener('touchend', (event) => {
    if (etat) {
        const touche = event.changedTouches[0] ?? etat.touche;
        if (etat.actif) {
            event.preventDefault(); // évite le clic fantôme après le dépôt
        }
        terminer(touche, true);
    }
}, { passive: false });

document.addEventListener('touchcancel', (event) => {
    if (etat) {
        terminer(event.changedTouches[0] ?? etat.touche, false);
    }
});

// Pas de menu contextuel ni de sélection de texte pendant l'appui long sur un élément déplaçable
document.addEventListener('contextmenu', (event) => {
    if (etat?.actif || (etat && event.target.closest?.('[draggable]'))) {
        event.preventDefault();
    }
});
