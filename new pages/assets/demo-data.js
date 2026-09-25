/* =========================================================
   PHOENIX — données d'exemple de l'espace demandeur
   À remplacer par les appels à l'API Laravel.
   ========================================================= */
const DEMO = {
  steps:[
    { key:'submitted', label:'Demande déposée' },
    { key:'paid',      label:'Paiement' },
    { key:'verified',  label:"Vérification par l'officier" },
    { key:'signed',    label:'Signature du maire' },
    { key:'ready',     label:'Copie disponible' }
  ],
  requests:[
    { id:'PHX-2026-004218', doc:'Acte de naissance', holder:'Marie France Ngoa', center:'yaounde-3', reason:'Perte',
      status:'complement', step:'paid', submittedAt:'2026-09-12T10:20:00', phone4:'4218',
      history:{ submitted:'2026-09-12T10:20:00', paid:'2026-09-12T10:31:00' },
      complement:{ what:'Nouvelle photo de votre pièce d\'identité',
        message:'Bonjour, la photo de votre CNI est floue : le numéro et la date de validité ne sont pas lisibles. Merci d\'envoyer une nouvelle photo bien nette, prise à plat et à la lumière du jour.',
        officer:'Alain Mbarga', askedAt:'2026-09-23T14:05:00', deadline:'2026-10-07' },
      thread:[
        { from:'system', at:'2026-09-12T10:20:00', text:'Demande déposée.' },
        { from:'system', at:'2026-09-12T10:31:00', text:'Paiement de 20 000 FCFA confirmé (MTN Mobile Money).' },
        { from:'officer', name:'Alain Mbarga, officier', at:'2026-09-23T14:05:00', text:'Bonjour, la photo de votre CNI est floue : le numéro et la date de validité ne sont pas lisibles. Merci d\'envoyer une nouvelle photo bien nette.' }
      ],
      payment:{ ref:'PAY-88213', method:'mtn-momo', amount:20000, at:'2026-09-12T10:31:00', status:'paid' }
    },
    { id:'PHX-2026-003540', doc:'Acte de naissance', holder:'Lucas Ngoa (enfant)', center:'yaounde-2', reason:'Détérioration',
      status:'verified', step:'verified', submittedAt:'2026-09-02T08:12:00', phone4:'3540',
      history:{ submitted:'2026-09-02T08:12:00', paid:'2026-09-02T08:20:00', verified:'2026-09-18T11:47:00' },
      thread:[
        { from:'system', at:'2026-09-02T08:12:00', text:'Demande déposée.' },
        { from:'system', at:'2026-09-02T08:20:00', text:'Paiement de 20 000 FCFA confirmé (Orange Money).' },
        { from:'officer', name:'Rose Nkolo, officier', at:'2026-09-18T11:47:00', text:'Acte retrouvé et vérifié. Le dossier est transmis au maire pour signature.' }
      ],
      payment:{ ref:'PAY-86002', method:'orange-money', amount:20000, at:'2026-09-02T08:20:00', status:'paid' }
    },
    { id:'PHX-2026-001107', doc:'Acte de naissance', holder:'Marie France Ngoa', center:'yaounde-3', reason:'Perte',
      status:'completed', step:'ready', submittedAt:'2026-06-03T09:00:00', phone4:'1107',
      history:{ submitted:'2026-06-03T09:00:00', paid:'2026-06-04T12:10:00', verified:'2026-06-10T10:00:00', signed:'2026-06-12T16:30:00', ready:'2026-06-12T16:31:00' },
      pickup:{ place:'Centre d\'état civil de Yaoundé III', hours:'Du lundi au vendredi, 8 h – 15 h 30 (horaires à confirmer)' },
      thread:[
        { from:'system', at:'2026-06-03T09:00:00', text:'Demande déposée.' },
        { from:'system', at:'2026-06-04T12:10:00', text:'Paiement de 20 000 FCFA confirmé (MTN Mobile Money).' },
        { from:'system', at:'2026-06-10T10:00:00', text:'Vérifiée par l\'officier.' },
        { from:'system', at:'2026-06-12T16:30:00', text:'Signée par le maire. Copie disponible.' }
      ],
      payment:{ ref:'PAY-70452', method:'mtn-momo', amount:20000, at:'2026-06-04T12:10:00', status:'paid' }
    },
    { id:'PHX-2026-000388', doc:'Acte de naissance', holder:'Marie France Ngoa', center:'yaounde-5', reason:'Perte',
      status:'rejected', step:'paid', submittedAt:'2026-03-15T09:00:00', phone4:'0388',
      history:{ submitted:'2026-03-15T09:00:00', paid:'2026-03-15T09:06:00' },
      rejection:'Aucun acte ne correspond dans le registre du centre de Yaoundé V. Votre naissance a probablement été déclarée dans un autre centre.',
      thread:[
        { from:'system', at:'2026-03-15T09:00:00', text:'Demande déposée.' },
        { from:'officer', name:'Officier de Yaoundé V', at:'2026-03-20T10:00:00', text:'Aucun acte ne correspond dans le registre de ce centre. Vérifiez le centre de déclaration.' }
      ],
      payment:{ ref:'PAY-51277', method:'orange-money', amount:20000, at:'2026-03-15T09:06:00', status:'refunded', refundedAt:'2026-03-27T10:00:00' }
    }
  ],
  methods:{ 'mtn-momo':'MTN Mobile Money', 'orange-money':'Orange Money' },
  find(id){ return this.requests.find(r => r.id === id); }
};
