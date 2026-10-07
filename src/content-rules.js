// Content policy shared by scripts/check.mjs (publish gate) and the admin
// panel (pre-save validation), so the panel rejects text that would fail CI.
export const bannedPhrases = [
  [/lorem ipsum|dolor sit amet/i, "Lorem placeholder"],
  [/\bTODO\b|\bFIXME\b/, "Implementation placeholder"],
  [/örnek yorum|örnek müşteri|demo yorumu/i, "Sample testimonial"],
  [
    /EPDK\s*onaylı|TEDAŞ\s*onaylı|FAT\s*belgeli/i,
    "Unconfirmed certification claim",
  ],
  [/\b100\s*%|%\s*100\s*(?:garanti|başarı)/i, "Absolute success claim"],
  [
    /500\+\s*proje|200\+\s*müşteri|15\+?\s*yıl|2010.dan bu yana/i,
    "Disallowed unsupported company metric",
  ],
  [/Özdenizcilik Gemi|Öz Denizcilik|Nığsa/i, "Incorrect customer name"],
  [/45[.,]000\s*(?:TL|₺)/i, "Unconfirmed annual savings claim"],
];

export const bannedLabelsTr = {
  "Lorem placeholder": "yer tutucu metin",
  "Implementation placeholder": "yer tutucu not (TODO/FIXME)",
  "Sample testimonial": "örnek/sahte müşteri yorumu",
  "Unconfirmed certification claim": "belgesi doğrulanmamış onay/sertifika ifadesi",
  "Absolute success claim": "mutlak başarı veya %100 iddiası",
  "Disallowed unsupported company metric": "doğrulanmamış şirket sayısı/süresi",
  "Incorrect customer name": "hatalı yazılmış müşteri adı",
  "Unconfirmed annual savings claim": "doğrulanmamış tasarruf iddiası",
};

export const findBannedPhrase = (text) =>
  bannedPhrases.find(([pattern]) => pattern.test(String(text ?? "")))?.[1] ??
  null;
