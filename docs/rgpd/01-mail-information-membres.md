# Modèle de mail d'information aux membres

Usage : envoi unique à tous les membres actifs, puis relance à J+30 aux personnes qui n'ont pas répondu.

Variables à remplacer : `{{prenom}}`, `{{lien_politique}}` (page `/confidentialite`), `{{lien_accuse}}` (lien d'accusé de lecture), `{{lien_consentement}}` et `{{lien_refus}}` (choix sur les communications facultatives), `{{email_contact}}`, `{{nom_president}}`. Pour la variante mineurs : `{{prenom_enfant}}` et `{{nom_enfant}}`.

---

## Version membres majeurs

**Objet :** Vos données personnelles sur le site de [NOM ASSO] : ce que nous en faisons

Bonjour {{prenom}},

Dans le cadre du règlement général sur la protection des données (RGPD), nous vous informons de la manière dont [NOM ASSO] utilise vos données personnelles sur son site de gestion des plannings.

**Ce que nous conservons**
Votre nom, prénom, adresse e-mail, téléphone (facultatif), identifiant de connexion, vos aptitudes (par exemple : surveillance, initiation), et vos réponses aux invitations.

**Pourquoi**
Pour organiser les plannings et les créneaux, et pour inviter les personnes dont les aptitudes sont nécessaires à une activité. Ce traitement est nécessaire à l'exécution de votre adhésion.

**Qui y accède**
Les membres du bureau et les bénévoles habilités à gérer le planning. Notre prestataire d'envoi de mails (Brevo) transmet les messages du site.

**Combien de temps**
Chaque année, le 31 août, les comptes et les invitations créés jusqu'à cette date sont supprimés.

**Vos droits**
Vous pouvez demander l'accès à vos données, leur rectification, leur effacement, la limitation du traitement, ou vous opposer au traitement, en écrivant à {{email_contact}} ou par courrier à [ADRESSE]. Nous vous répondons sous un mois. Vous pouvez aussi déposer une réclamation auprès de la CNIL (cnil.fr).

Pour le détail, consultez notre politique de confidentialité : {{lien_politique}}

**Accusé de lecture (obligatoire pour la gestion du planning)**
Cliquez sur le lien suivant pour confirmer que vous avez pris connaissance de ces informations : {{lien_accuse}}

Ce choix n'est pas facultatif. Il permet seulement à l'association de savoir que vous avez été informé.

**Communications facultatives (votre choix)**
Nous aimerions vous adresser des informations qui ne concernent pas le planning : newsletter, invitations à des événements, sollicitations de bénévolat ou de dons. Ce choix est libre et vous pouvez le modifier à tout moment.

- ☐ J'accepte de recevoir ces communications → {{lien_consentement}}
- ☐ Je refuse → {{lien_refus}}

Si vous ne répondez pas, nous ne vous enverrons pas ces communications.

Merci pour votre engagement au sein de [NOM ASSO].

{{nom_president}}
Président de [NOM ASSO]

---

## Version représentants légaux (membre mineur)

**Objet :** Données personnelles de {{prenom_enfant}} {{nom_enfant}} sur le site de [NOM ASSO]

Bonjour,

Dans le cadre du règlement général sur la protection des données (RGPD), nous vous informons de la manière dont [NOM ASSO] utilise les données personnelles de {{prenom_enfant}} {{nom_enfant}}, membre du club, sur son site de gestion des plannings.

Les informations suivantes sont conservées : nom, prénom, adresse e-mail du représentant légal, téléphone (facultatif), identifiant de connexion et aptitudes de {{prenom_enfant}}.

Elles servent à organiser les plannings et les créneaux. Elles sont accessibles au bureau et aux bénévoles habilités. Notre prestataire d'envoi de mails (Brevo) transmet les messages du site.

Chaque année, le 31 août, les comptes et les invitations créés jusqu'à cette date sont supprimés.

Vous pouvez accéder à ces données, les faire rectifier ou effacer, et vous opposer à leur traitement, en écrivant à {{email_contact}}. Nous vous répondons sous un mois. Vous pouvez aussi saisir la CNIL.

Pour le détail : {{lien_politique}}

**Accusé de lecture :** {{lien_accuse}}

**Communications facultatives :** nous vous proposons de recevoir des informations qui ne concernent pas le planning (newsletter, événements). Ce choix est le vôtre.

- ☐ J'accepte que l'association m'adresse ces communications → {{lien_consentement}}
- ☐ Je refuse → {{lien_refus}}

**Photos :** si nous souhaitons publier une photo de {{prenom_enfant}}, nous vous demanderons un accord écrit séparé.

Cordialement,

{{nom_president}}
Président de [NOM ASSO]

---

## Relance à J+30 (sans réponse à l'accusé de lecture)

**Objet :** Rappel : information sur vos données personnelles — [NOM ASSO]

Bonjour {{prenom}},

Nous vous avons envoyé il y a un mois une information sur le traitement de vos données personnelles sur le site de [NOM ASSO]. Nous n'avons pas encore reçu votre accusé de lecture.

Vous pouvez consulter le détail ici : {{lien_politique}}, et confirmer votre lecture ici : {{lien_accuse}}.

Cordialement,

Le bureau de [NOM ASSO]
