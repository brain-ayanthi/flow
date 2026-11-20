<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Job Card · {{ $order->job_number }}</title>
    <style>
        @page { size: A5 landscape; margin: 4mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; color: #111827; font-family: Arial, Helvetica, sans-serif; }
        body { background: #e9edf2; font-size: 9.6pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .print-toolbar { position: sticky; top: 0; z-index: 5; display: flex; justify-content: flex-end; gap: 8px; padding: 11px 18px; background: #152238; color: #fff; }
        .print-toolbar button { border: 1px solid #ccd5df; border-radius: 5px; padding: 8px 15px; background: #fff; color: #26364a; font-size: 14.4px; font-weight: 700; cursor: pointer; }
        .print-toolbar .primary { border-color: #1676d2; background: #1676d2; color: #fff; }
        .job-card { width: min(1380px, calc(100vw - 28px)); min-width: 1080px; margin: 12px auto; padding: 9px; background: #fff; box-shadow: 0 4px 20px rgba(17, 33, 54, .16); }
        .card-top { display: flex; align-items: center; justify-content: space-between; gap: 15px; height: 82px; margin-bottom: 8px; }
        .shop-name { min-width: 0; flex: 1; padding-left: 10px; overflow: hidden; color: #111; font-size: clamp(30px, 3.3vw, 54px); line-height: 1; font-weight: 400; white-space: nowrap; text-overflow: ellipsis; }
        .document-banner { display: grid; grid-template-columns: 1fr 1.05fr; width: 42%; min-width: 440px; height: 78px; overflow: hidden; border-radius: 0 14px 14px 0; background: linear-gradient(110deg, #810bb2, #15176c 70%); color: #fff; }
        .document-brand { display: grid; justify-items: center; align-content: center; padding: 3px 8px; text-align: center; font-weight: 900; line-height: 1.05; }
        .document-brand .brand-top { font-size: 20.4px; letter-spacing: .5px; }
        .document-brand .brand-bottom { font-size: 19.2px; }
        .document-brand .job-label { margin-top: 4px; padding: 3px 23px; border-radius: 14px; background: #ffe100; color: #111; font-size: 20.4px; }
        .document-ref { display: flex; flex-direction: column; justify-content: center; margin: 6px 7px 6px 0; padding: 7px 11px; border-radius: 12px; background: #fff; color: #111; font-size: 13.2px; font-weight: 700; }
        .document-ref .ref-line { display: flex; align-items: center; justify-content: space-between; gap: 7px; padding-bottom: 4px; border-bottom: 1px solid #abb1bd; }
        .document-ref .ref-number { color: #e51525; font-size: 21.6px; font-weight: 900; white-space: nowrap; }
        .document-ref .date-line { padding-top: 5px; font-weight: 400; }
        .columns { display: grid; grid-template-columns: 1.25fr 1fr 1.1fr; gap: 8px; height: 660px; }
        .column { display: flex; min-width: 0; min-height: 0; flex-direction: column; gap: 6px; }
        .panel { min-width: 0; overflow: hidden; border: 1px solid var(--accent); border-radius: 11px; background: #fff; }
        .panel-heading { display: flex; align-items: center; min-height: 27px; padding: 3px 10px; background: var(--accent); color: #fff; font-size: 14.4px; line-height: 1.05; font-weight: 900; letter-spacing: .3px; text-transform: uppercase; }
        .panel-body { padding: 5px 8px; }
        .blue { --accent: #0872d5; --pale: #eef6ff; }
        .pink { --accent: #ed0875; --pale: #fff0f6; }
        .orange { --accent: #ff6900; --pale: #fff5ec; }
        .green { --accent: #069e63; --pale: #effbf5; }
        .purple { --accent: #7b0aa3; --pale: #f8effc; }
        .teal { --accent: #118397; --pale: #effafd; }
        .red { --accent: #e9002d; --pale: #fff1f4; }
        .customer-panel { height: 140px; flex: 0 0 140px; }
        .job-panel { height: 132px; flex: 0 0 132px; }
        .material-panel { height: 124px; flex: 0 0 124px; }
        .delivery-panel { height: 171px; flex: 0 0 171px; }
        .signature-row { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; height: 72px; flex: 1 1 auto; min-height: 64px; }
        .signature-panel { height: 100%; }
        .signature-panel .panel-heading { justify-content: center; }
        .signature-box { position: relative; height: calc(100% - 27px); padding: 4px 9px; background: #fff; }
        .signature-box::before { position: absolute; right: 8px; bottom: 22px; left: 8px; height: 1px; background: #3a4859; content: ""; }
        .signature-box span { position: absolute; bottom: 4px; left: 9px; color: #29384b; font-size: 10px; }
        .field-line { display: grid; grid-template-columns: 29% 8px minmax(0, 1fr); align-items: end; min-height: 23px; gap: 3px; font-size: 10px; line-height: 1.1; }
        .field-label { white-space: nowrap; }
        .field-value { display: block; min-width: 0; min-height: 17px; overflow: hidden; border-bottom: 1px dotted #596575; padding: 2px 2px 1px; white-space: nowrap; text-overflow: ellipsis; }
        .address-line { min-height: 27px; align-items: start; }
        .address-line .field-value { min-height: 25px; white-space: normal; line-height: 1.2; }
        .artwork-line { align-items: start; min-height: 33px; }
        .artwork-line .field-value { min-height: 31px; white-space: normal; }
        .remarks-line { min-height: 25px; }
        .pair-line { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; }
        .pair-line .field-line { grid-template-columns: auto 7px minmax(0, 1fr); min-height: 22px; gap: 3px; }
        .choice-row { display: flex; align-items: center; flex-wrap: wrap; gap: 7px 11px; min-height: 22px; font-size: 10px; }
        .choice { display: inline-flex; align-items: center; gap: 3px; white-space: nowrap; }
        .box { display: inline-flex; width: 11px; height: 11px; flex: 0 0 11px; align-items: center; justify-content: center; border: 1px solid #283648; border-radius: 1px; background: rgba(255,255,255,.78); color: #111; font-size: 10.8px; line-height: 1; font-weight: 900; }
        .box.checked::after { content: "✓"; }
        .job-type-row { gap: 9px; min-height: 25px; font-size: 10px; font-weight: 600; }
        .compact-line { min-height: 24px; }
        .item-summary { display: flex; flex-wrap: wrap; gap: 2px 4px; align-content: flex-start; min-height: 21px; max-height: 37px; overflow: hidden; padding: 2px 0; border-bottom: 1px dotted #596575; }
        .category-chip { display: inline-block; padding: 2px 4px; border-radius: 3px; background: #f0f4f8; color: #26374b; font-size: 10px; font-weight: 800; }
        .material-choice { gap: 6px; min-height: 23px; }
        .delivery-choice { gap: 10px; min-height: 24px; }
        .delivery-panel .field-line { min-height: 22px; }
        .expected-row { grid-template-columns: 33% 8px minmax(0, 1fr); }
        .small-note { color: #5b6675; font-size: 10px; line-height: 1.15; }

        .spec-panel { height: 246px; flex: 0 0 246px; }
        .spec-panel .panel-body { padding: 3px 6px 4px; }
        .spec-row { display: grid; grid-template-columns: 39% minmax(0, 1fr); align-items: center; min-height: 27px; gap: 4px; border-bottom: 1px solid #dce2e8; font-size: 10px; }
        .spec-row-label { font-size: 10px; }
        .spec-row .choice-row { min-height: 22px; gap: 6px; font-size: 10px; }
        .spec-row .box { width: 10px; height: 10px; flex-basis: 10px; }
        .spec-row .field-line { min-height: 20px; grid-template-columns: minmax(0, 1fr) 30px; gap: 4px; }
        .spec-row .field-value { min-height: 15px; }
        .spec-sub-label { white-space: nowrap; }
        .special-instructions { display: block; padding-top: 4px; font-size: 10px; }
        .special-instructions .field-line { grid-template-columns: 31% 8px minmax(0, 1fr); min-height: 20px; }
        .special-instructions .field-value { min-height: 17px; }
        .payment-panel { height: 137px; flex: 0 0 137px; }
        .payment-panel .panel-body { padding: 4px 8px 6px; }
        .payment-line { display: grid; grid-template-columns: 23% 8px minmax(0, 1fr); align-items: end; min-height: 23px; gap: 3px; font-size: 10px; }
        .payment-line .field-value { min-height: 17px; }
        .payment-methods { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; min-height: 23px; font-size: 10px; }
        .payment-total { display: flex; align-items: center; justify-content: space-between; min-height: 33px; margin-top: 3px; padding: 3px 8px; border: 1px solid #d7b0e9; border-radius: 7px; background: #f8eafa; font-size: 13.2px; font-weight: 900; }
        .payment-total strong { display: block; min-width: 42%; min-height: 25px; padding: 4px 7px; border: 1px solid #c99ddd; border-radius: 5px; background: #fff; text-align: right; }
        .tracking-panel { min-height: 0; flex: 1 1 auto; }
        .tracking-panel .panel-body { padding: 0; }
        .tracking-table { display: grid; grid-template-columns: 29% 47% 16% 8%; width: 100%; font-size: 10px; }
        .track-head, .track-row { display: contents; }
        .track-head > span { display: flex; align-items: center; justify-content: center; min-height: 18px; padding: 1px; border-right: 1px solid #aab8c1; border-bottom: 1px solid #aab8c1; background: #e9f2f5; font-size: 10px; font-weight: 900; text-align: center; }
        .track-row > span { display: flex; min-width: 0; align-items: center; min-height: 26px; padding: 2px 3px; border-right: 1px solid #c5d0d8; border-bottom: 1px solid #c5d0d8; font-size: 10px; line-height: 1.05; }
        .track-row > span:first-child { font-weight: 600; }
        .track-statuses { display: flex !important; align-items: center !important; justify-content: space-around; gap: 2px; }
        .track-status { display: inline-flex; align-items: center; gap: 1px; white-space: nowrap; font-size: 10px; }
        .track-status .box { width: 8px; height: 8px; flex-basis: 8px; font-size: 10px; }

        .digital-panel { display: flex; min-height: 0; flex: 1 1 auto; flex-direction: column; border-color: #e9002d; }
        .digital-panel > .panel-heading { min-height: 29px; padding-left: 13px; font-size: 15.6px; }
        .product-stack { display: flex; min-height: 0; flex: 1 1 auto; flex-direction: column; gap: 5px; padding: 5px; }
        .product-card { position: relative; flex: 1 1 0; min-height: 0; overflow: hidden; border-radius: 9px; padding: 5px 7px; }
        .product-card h3 { position: relative; z-index: 1; display: inline-block; max-width: 73%; margin: 0 0 3px; padding: 3px 9px; border-radius: 5px; background: var(--product-title); color: #fff; font-size: 13.2px; line-height: 1.05; }
        .product-card .product-body { position: relative; z-index: 1; width: 76%; font-size: 10px; line-height: 1.18; }
        .product-line { display: flex; align-items: end; min-height: 15px; gap: 4px; }
        .product-line strong { white-space: nowrap; }
        .product-line span { flex: 1; min-width: 0; min-height: 13px; overflow: hidden; border-bottom: 1px dotted #596575; padding: 1px 2px; white-space: nowrap; text-overflow: ellipsis; }
        .product-item { display: flex; align-items: center; justify-content: space-between; gap: 5px; min-height: 13px; padding: 1px 0; border-bottom: 1px dotted rgba(63,73,83,.28); font-size: 10px; }
        .product-item strong { min-width: 0; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }
        .product-item span { flex: 0 0 auto; white-space: nowrap; }
        .product-options { display: grid; grid-template-columns: 1fr 1fr; gap: 2px 7px; margin-top: 3px; font-size: 10px; }
        .product-options .choice { gap: 2px; }
        .product-options .box { width: 9px; height: 9px; flex-basis: 9px; font-size: 10px; }
        .product-banner { --product-title: #ed1142; flex: 1.16 1 0; background: linear-gradient(110deg, #ffe8ee, #fff4f7); }
        .product-board { --product-title: #f04c14; flex: .98 1 0; background: linear-gradient(110deg, #ffeadb, #fff4eb); }
        .product-sticker { --product-title: #008c82; flex: .92 1 0; background: linear-gradient(110deg, #e4f7f0, #f1fbf4); }
        .product-finishing { --product-title: #bd830d; flex: 1.76 1 0; background: linear-gradient(110deg, #fff3c7, #fff9e7); }
        .product-sign { --product-title: #1485a4; flex: .94 1 0; background: linear-gradient(110deg, #e1f5ff, #f0faff); }
        .product-generic { --product-title: #536273; flex: .9 1 0; min-height: 47px; background: #f4f6f8; }
        .product-generic .product-body { width: 100%; }
        .visual { position: absolute; z-index: 0; top: 7px; right: 8px; width: 22%; height: 68%; }
        .banner-visual { transform: rotate(-8deg); border: 2px solid rgba(255,255,255,.86); border-radius: 4px; background: radial-gradient(circle at 72% 33%, #ffe54d 0 7%, transparent 8%), radial-gradient(circle at 38% 56%, #fb3265 0 12%, transparent 13%), linear-gradient(135deg, #7037d5 0%, #00a9c0 32%, #ffb822 55%, #f32651 78%, #6a45d3); box-shadow: 0 3px 6px rgba(39,45,61,.24); }
        .banner-visual::before, .banner-visual::after { position: absolute; bottom: -4px; width: 8px; height: 8px; border: 2px solid #616d78; border-radius: 50%; background: #fff; content: ""; }
        .banner-visual::before { left: 5px; }
        .banner-visual::after { right: 5px; }
        .board-visual { top: 10px; right: 8px; width: 22%; height: 61%; border: 1px solid #9aa5af; border-radius: 2px; background: #fff; box-shadow: 0 4px #d5dbe0, 0 7px #f8f9fa, 0 9px #afb9c2, 0 12px #e4e7ea, 0 14px #aab4bd; transform: skewY(-10deg); }
        .roll-visual { top: 9px; right: 8px; width: 22%; height: 54%; border-radius: 20px; background: repeating-linear-gradient(105deg, #e9f3f5 0 7px, #00a69a 8px 14px, #f4b347 15px 20px, #df4a6f 21px 26px); box-shadow: inset 5px 0 0 rgba(255,255,255,.9), inset -5px 0 0 rgba(44,61,74,.25), 0 3px 5px rgba(41,53,65,.2); }
        .finish-row { display: flex; align-items: center; gap: 4px; min-height: 16px; font-size: 10px; }
        .finish-row strong { flex: 0 0 41%; font-weight: 700; }
        .finish-row .choice-row { gap: 6px; min-height: 15px; font-size: 10px; }
        .finish-row .box { width: 9px; height: 9px; flex-basis: 9px; font-size: 10px; }
        .finish-row .field-value { flex: 1; min-width: 0; min-height: 13px; border-bottom: 1px dotted #596575; }
        .additional-item { display: grid; grid-template-columns: 32% minmax(0,1fr); gap: 3px; min-height: 14px; padding: 2px 0; border-bottom: 1px dotted #adb7c0; font-size: 10px; }
        .additional-item b { color: #344154; }
        .additional-item span { overflow: hidden; white-space: nowrap; text-overflow: ellipsis; }

        @media screen and (max-width: 1100px) {
            .job-card { width: 1080px; margin-left: 12px; }
        }

        @media print {
            .print-toolbar { display: none !important; }
            html, body { width: 202mm; height: 140mm; background: #fff; }
            .job-card { width: 202mm; height: 140mm; min-width: 0; margin: 0; padding: 0; overflow: hidden; box-shadow: none; }
            .card-top { height: 16.5mm; margin-bottom: 1.5mm; }
            .shop-name { padding-left: 2mm; font-size: 26.4pt; }
            .document-banner { width: 42%; min-width: 0; height: 15mm; border-radius: 0 2mm 2mm 0; }
            .document-brand .brand-top { font-size: 8.4pt; }
            .document-brand .brand-bottom { font-size: 7.8pt; }
            .document-brand .job-label { margin-top: .5mm; padding: .5mm 4mm; font-size: 8.4pt; }
            .document-ref { margin: 1mm 1mm 1mm 0; padding: 1mm 1.5mm; border-radius: 1.5mm; font-size: 6pt; }
            .document-ref .ref-number { font-size: 8.4pt; }
            .document-ref .date-line { padding-top: .5mm; }
            .columns { grid-template-columns: 1.25fr 1fr 1.1fr; gap: 1.2mm; height: 122mm; }
            .column { gap: .9mm; }
            .panel { border-width: .25mm; border-radius: 1.5mm; }
            .panel-heading { min-height: 4.5mm; padding: .4mm 1.4mm; font-size: 7pt; }
            .panel-body { padding: .6mm 1.1mm; }
            .customer-panel { height: 24mm; flex-basis: 24mm; }
            .job-panel { height: 22mm; flex-basis: 22mm; }
            .material-panel { height: 20mm; flex-basis: 20mm; }
            .delivery-panel { height: 29mm; flex-basis: 29mm; }
            .signature-row { height: auto; min-height: 18mm; gap: 1mm; }
            .signature-panel { height: 100%; }
            .signature-panel .panel-heading { min-height: 4.5mm; }
            .signature-box { height: calc(100% - 4.5mm); padding: .6mm 1mm; }
            .signature-box::before { right: 1mm; bottom: 4mm; left: 1mm; }
            .signature-box span { bottom: .5mm; left: 1mm; font-size: 5.4pt; }
            .field-line { grid-template-columns: 29% 1.2mm minmax(0, 1fr); min-height: 3.25mm; gap: .35mm; font-size: 5.8pt; }
            .field-value { min-height: 2.6mm; padding: .15mm .3mm 0; }
            .address-line { min-height: 4mm; }
            .address-line .field-value { min-height: 3.6mm; }
            .artwork-line { min-height: 5.5mm !important; }
            .artwork-line .field-value { min-height: 5mm; }
            .remarks-line { min-height: 3.4mm !important; }
            .pair-line { gap: .8mm; }
            .pair-line .field-line { grid-template-columns: auto 1mm minmax(0, 1fr); min-height: 3.15mm; gap: .3mm; }
            .choice-row { gap: .6mm 1.3mm; min-height: 3.2mm; font-size: 5.4pt; }
            .choice { gap: .4mm; }
            .box { width: 1.9mm; height: 1.9mm; flex-basis: 1.9mm; font-size: 5.8pt; }
            .job-type-row { gap: 1.1mm; min-height: 3.5mm; font-size: 5.4pt; }
            .compact-line { min-height: 3.15mm; }
            .item-summary { min-height: 2.8mm; max-height: 4.6mm; gap: .3mm .5mm; padding: .2mm 0; }
            .category-chip { padding: .2mm .5mm; font-size: 5pt; }
            .material-choice { gap: .8mm; min-height: 3.2mm; }
            .delivery-choice { gap: .8mm; min-height: 3.3mm; }
            .delivery-panel .field-line { min-height: 3.15mm; }
            .expected-row { grid-template-columns: 33% 1.2mm minmax(0, 1fr); }
            .small-note { font-size: 5pt; }
            .spec-panel { height: 45mm; flex-basis: 45mm; }
            .spec-panel .panel-body { padding: .3mm .6mm .4mm; }
            .spec-row { grid-template-columns: 39% minmax(0, 1fr); min-height: 4.15mm; gap: .4mm; font-size: 5pt; }
            .spec-row-label { font-size: 5pt; }
            .spec-row .choice-row { min-height: 3.5mm; gap: .65mm; font-size: 5pt; }
            .spec-row .box { width: 1.7mm; height: 1.7mm; flex-basis: 1.7mm; }
            .spec-row .field-line { min-height: 3.3mm; grid-template-columns: minmax(0, 1fr) 6mm; gap: .4mm; }
            .spec-row .field-value { min-height: 2.6mm; }
            .special-instructions { padding-top: .3mm; font-size: 5pt; }
            .special-instructions .field-line { grid-template-columns: 31% 1.2mm minmax(0, 1fr); min-height: 3.1mm; }
            .special-instructions .field-value { min-height: 2.6mm; }
            .payment-panel { height: 25mm; flex-basis: 25mm; }
            .payment-panel .panel-body { padding: .4mm .9mm .5mm; }
            .payment-line { grid-template-columns: 23% 1.2mm minmax(0, 1fr); min-height: 3.2mm; gap: .35mm; font-size: 5.4pt; }
            .payment-line .field-value { min-height: 2.6mm; }
            .payment-methods { gap: .7mm; min-height: 3.2mm; font-size: 5pt; }
            .payment-methods .box { width: 1.7mm; height: 1.7mm; flex-basis: 1.7mm; }
            .payment-total { min-height: 5.7mm; margin-top: .3mm; padding: .35mm .8mm; border-radius: 1mm; font-size: 6.8pt; }
            .payment-total strong { min-height: 4.3mm; padding: .4mm .7mm; border-radius: .7mm; }
            .tracking-panel { flex: 1 1 auto; }
            .tracking-table { grid-template-columns: 29% 47% 16% 8%; font-size: 5pt; }
            .track-head > span { min-height: 3.8mm; padding: .2mm; font-size: 5pt; }
            .track-row > span { min-height: 4.45mm; padding: .2mm .3mm; font-size: 5pt; }
            .track-statuses { gap: .2mm; }
            .track-status { gap: .1mm; font-size: 5pt; }
            .track-status .box { width: 1.6mm; height: 1.6mm; flex-basis: 1.6mm; font-size: 4.6pt; }
            .digital-panel > .panel-heading { min-height: 4.5mm; padding-left: 1.5mm; font-size: 7.6pt; }
            .product-stack { gap: .7mm; padding: .7mm; }
            .product-card { padding: .6mm .8mm; border-radius: 1.1mm; }
            .product-card h3 { margin-bottom: .25mm; padding: .4mm 1mm; border-radius: .7mm; font-size: 6.2pt; }
            .product-card .product-body { width: 76%; font-size: 5pt; line-height: 1.05; }
            .product-line { min-height: 2.4mm; gap: .4mm; }
            .product-line span { min-height: 2.1mm; padding: .1mm .2mm; }
            .product-item { min-height: 2.1mm; gap: .5mm; padding: .1mm 0; font-size: 5pt; }
            .product-options { gap: .25mm .7mm; margin-top: .25mm; font-size: 5pt; }
            .product-options .choice { gap: .2mm; }
            .product-options .box { width: 1.5mm; height: 1.5mm; flex-basis: 1.5mm; font-size: 4.1pt; }
            .product-banner { flex: 1.16 1 0; }
            .product-board { flex: .98 1 0; }
            .product-sticker { flex: .92 1 0; }
            .product-finishing { flex: 1.76 1 0; }
            .product-sign { flex: .94 1 0; }
            .product-generic { flex: .9 1 0; min-height: 9mm; }
            .visual { top: .8mm; right: .8mm; }
            .finish-row { gap: .3mm; min-height: 2.7mm; font-size: 5pt; }
            .finish-row strong { flex-basis: 41%; }
            .finish-row .choice-row { gap: .5mm; min-height: 2.6mm; font-size: 5pt; }
            .finish-row .box { width: 1.5mm; height: 1.5mm; flex-basis: 1.5mm; font-size: 4pt; }
            .finish-row .field-value { min-height: 2.1mm; }
            .additional-item { grid-template-columns: 32% minmax(0,1fr); gap: .3mm; min-height: 2.5mm; padding: .2mm 0; font-size: 5pt; }
        }
    </style>
</head>
<body>
    <div class="print-toolbar">
        <button class="primary" type="button" onclick="window.print()">Print Job Card</button>
        <button type="button" onclick="closeJobSheet()">Close</button>
    </div>

    <main class="job-card">
        <header class="card-top">
            <div class="shop-name">{{ $workspace?->name ?? 'PressFlow' }}</div>
            <div class="document-banner">
                <div class="document-brand">
                    <span class="brand-top">DIGITAL &amp; OFFSET</span>
                    <span class="brand-bottom">PRINT SHOP</span>
                    <span class="job-label">JOB CARD</span>
                </div>
                <div class="document-ref">
                    <div class="ref-line"><span>Job Card No:</span><strong class="ref-number">{{ $order->job_number }}</strong></div>
                    <div class="date-line">Date: {{ $order->job_date?->format('d/m/Y') ?? '____ / ____ / ______' }}</div>
                </div>
            </div>
        </header>

        <div class="columns">
            <div class="column left-column">
                <section class="panel blue customer-panel">
                    <div class="panel-heading">Customer Details</div>
                    <div class="panel-body">
                        <div class="field-line"><span class="field-label">Customer Name</span><span>:</span><span class="field-value">{{ $customer?->name ?? '' }}</span></div>
                        <div class="field-line"><span class="field-label">Company / Organization</span><span>:</span><span class="field-value"></span></div>
                        <div class="field-line address-line"><span class="field-label">Address</span><span>:</span><span class="field-value">{{ $customer?->billing_address ?: ($customer?->shipping_address ?? '') }}</span></div>
                        <div class="pair-line">
                            <div class="field-line"><span class="field-label">Contact No</span><span>:</span><span class="field-value">{{ $order->contact_phone ?: ($customer?->phone ?? '') }}</span></div>
                            <div class="field-line"><span class="field-label">Email</span><span>:</span><span class="field-value">{{ $customer?->email ?? '' }}</span></div>
                        </div>
                    </div>
                </section>

                <section class="panel pink job-panel">
                    <div class="panel-heading">Job Details</div>
                    <div class="panel-body">
                        <div class="choice-row job-type-row">
                            <strong>Job Type:</strong>
                            <span class="choice"><i class="box {{ $selectedJobType === 'digital' ? 'checked' : '' }}"></i>Digital Print</span>
                            <span class="choice"><i class="box {{ $selectedJobType === 'offset' ? 'checked' : '' }}"></i>Offset Print</span>
                            <span class="choice"><i class="box {{ $selectedJobType === 'other' ? 'checked' : '' }}"></i>Others</span>
                        </div>
                        <div class="field-line compact-line"><span class="field-label">Product / Item</span><span>:</span><span class="field-value">{{ $productSummary }}</span></div>
                        <div class="field-line artwork-line"><span class="field-label">Description / Artwork</span><span>:</span><span class="field-value">{{ $order->description ?: $order->title }}</span></div>
                        <div class="item-summary">
                            @foreach($printItems as $printItem)
                                <span class="category-chip">{{ $printItem['category_label'] }}</span>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="panel orange material-panel">
                    <div class="panel-heading">Material Details</div>
                    <div class="panel-body">
                        <div class="field-line compact-line"><span class="field-label">Paper / Material</span><span>:</span><span class="field-value">{{ $materialSummary }}</span></div>
                        <div class="field-line compact-line"><span class="field-label">Size</span><span>:</span><span class="field-value">{{ $sizeSummary }}</span></div>
                        <div class="choice-row material-choice"><strong>Color:</strong><span class="choice"><i class="box"></i>Full Color</span><span class="choice"><i class="box"></i>Black &amp; White</span><span class="choice"><i class="box"></i>Spot Color</span></div>
                        <div class="field-line compact-line"><span class="field-label">Quantity</span><span>:</span><span class="field-value">{{ $quantitySummary }}</span></div>
                    </div>
                </section>

                <section class="panel green delivery-panel">
                    <div class="panel-heading">Delivery Details</div>
                    <div class="panel-body">
                        <div class="choice-row delivery-choice"><strong>Delivery Method:</strong><span class="choice"><i class="box"></i>Pick Up</span><span class="choice"><i class="box"></i>Delivery</span><span class="choice"><i class="box"></i>Courier</span><span class="choice"><i class="box"></i>Others</span></div>
                        <div class="pair-line">
                            <div class="field-line expected-row"><span class="field-label">Expected Delivery</span><span>:</span><span class="field-value">{{ $order->due_at?->format('d/m/Y') ?? '' }}</span></div>
                            <div class="field-line"><span class="field-label">Actual Delivery Date</span><span>:</span><span class="field-value">{{ $order->completed_at?->format('d/m/Y') ?? '' }}</span></div>
                        </div>
                        <div class="field-line compact-line"><span class="field-label">Delivered To</span><span>:</span><span class="field-value">{{ $deliveryAddress ?? '' }}</span></div>
                        <div class="pair-line">
                            <div class="field-line"><span class="field-label">Name</span><span>:</span><span class="field-value">{{ $order->contact_name ?: ($customer?->name ?? '') }}</span></div>
                            <div class="field-line"><span class="field-label">Contact No</span><span>:</span><span class="field-value">{{ $order->contact_phone ?: ($customer?->phone ?? '') }}</span></div>
                        </div>
                        <div class="field-line remarks-line"><span class="field-label">Remarks</span><span>:</span><span class="field-value">{{ $order->notes ?? '' }}</span></div>
                    </div>
                </section>

                <div class="signature-row">
                    <section class="panel orange signature-panel">
                        <div class="panel-heading">Customer Signature</div>
                        <div class="signature-box"><span>Date: ____ / ____ / ______</span></div>
                    </section>
                    <section class="panel blue signature-panel">
                        <div class="panel-heading" style="justify-content:center;background:#087fcb">Prepared By</div>
                        <div class="signature-box"><span>{{ $preparedBy }} &nbsp; · &nbsp; Date: ____ / ____ / ______</span></div>
                    </section>
                </div>
            </div>

            <div class="column middle-column">
                <section class="panel green spec-panel">
                    <div class="panel-heading">Job Specifications</div>
                    <div class="panel-body">
                        <div class="spec-row"><span class="spec-row-label">Design Provided</span><span class="choice-row"><span class="choice"><i class="box"></i>Yes</span><span class="choice"><i class="box"></i>No</span></span></div>
                        <div class="spec-row"><span class="spec-row-label">File Format</span><span class="choice-row"><span class="choice"><i class="box"></i>AI</span><span class="choice"><i class="box"></i>PSD</span><span class="choice"><i class="box"></i>PDF</span><span class="choice"><i class="box"></i>Others</span></span></div>
                        <div class="spec-row"><span class="spec-row-label">Resolution</span><span class="field-line"><span class="field-value"></span><span class="spec-sub-label">DPI</span></span></div>
                        <div class="spec-row"><span class="spec-row-label">Bleed</span><span class="choice-row"><span class="choice"><i class="box"></i>Yes</span><span class="choice"><i class="box"></i>No</span></span></div>
                        <div class="spec-row"><span class="spec-row-label">Lamination</span><span class="choice-row"><span class="choice"><i class="box"></i>Yes</span><span class="choice"><i class="box"></i>No</span><span class="spec-sub-label">Type:</span><span class="field-value" style="flex:1;min-width:10mm"></span></span></div>
                        <div class="spec-row"><span class="spec-row-label">Binding / Finishing</span><span class="choice-row"><span class="choice"><i class="box"></i>Cutting</span><span class="choice"><i class="box"></i>Folding</span><span class="choice"><i class="box"></i>Binding</span><span class="choice"><i class="box"></i>Others</span></span></div>
                        <div class="special-instructions">
                            <div class="field-line"><span class="field-label">Special Instructions</span><span>:</span><span class="field-value">{{ $order->notes ?? '' }}</span></div>
                            <div class="field-line"><span></span><span></span><span class="field-value"></span></div>
                            <div class="field-line"><span></span><span></span><span class="field-value"></span></div>
                        </div>
                    </div>
                </section>

                <section class="panel purple payment-panel">
                    <div class="panel-heading">Payment Details</div>
                    <div class="panel-body">
                        <div class="payment-line"><span>Advance {{ $currencyLabel }}</span><span>:</span><span class="field-value">{{ number_format($advanceAmount, 2) }}</span></div>
                        <div class="payment-line"><span>Balance {{ $currencyLabel }}</span><span>:</span><span class="field-value">{{ number_format($balanceAmount, 2) }}</span></div>
                        <div class="payment-methods"><strong>Payment Method:</strong><span class="choice"><i class="box {{ $paymentChecks['cash'] ? 'checked' : '' }}"></i>Cash</span><span class="choice"><i class="box {{ $paymentChecks['bank'] ? 'checked' : '' }}"></i>Bank Transfer</span><span class="choice"><i class="box {{ $paymentChecks['other'] ? 'checked' : '' }}"></i>Others</span><span class="small-note">{{ $paymentMethodNames }}</span></div>
                        <div class="payment-total"><span>Total Amount ({{ $currencyLabel }})</span><strong>{{ number_format((float) $order->total, 2) }}</strong></div>
                    </div>
                </section>

                <section class="panel teal tracking-panel">
                    <div class="panel-heading">Production Process Tracking</div>
                    <div class="panel-body">
                        <div class="tracking-table">
                            <div class="track-head"><span>Process</span><span>Status</span><span>Date / Time</span><span>By</span></div>
                            @foreach($trackingRows as $row)
                                <div class="track-row">
                                    <span>{{ $row['name'] }}</span>
                                    <span class="track-statuses">
                                        <span class="track-status"><i class="box {{ $row['status'] === 'pending' ? 'checked' : '' }}"></i>Pending</span>
                                        <span class="track-status"><i class="box {{ $row['status'] === 'in_progress' ? 'checked' : '' }}"></i>In Progress</span>
                                        <span class="track-status"><i class="box {{ $row['status'] === 'completed' ? 'checked' : '' }}"></i>Completed</span>
                                    </span>
                                    <span></span><span></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            </div>

            <div class="column right-column">
                <section class="panel red digital-panel">
                    <div class="panel-heading">Digital Print</div>
                    <div class="product-stack">
                        <section class="product-card product-banner">
                            <div class="visual banner-visual" aria-hidden="true"></div>
                            <h3>Flex Banner</h3>
                            <div class="product-body">
                                @forelse($productGroups['flexBanner'] as $item)
                                    <div class="product-item"><strong>{{ $item['detail'] }}</strong><span>{{ $item['size'] }} · {{ $item['quantity'] }}</span></div>
                                @empty
                                    <div class="product-line"><strong>Size</strong><span></span></div>
                                @endforelse
                                <div class="product-options">
                                    <span>Rings</span><span class="choice"><i class="box"></i>Yes &nbsp; <i class="box"></i>No</span>
                                    <span>PVC Pole</span><span class="choice"><i class="box"></i>Yes &nbsp; <i class="box"></i>No</span>
                                    <span>Gum Pocket</span><span class="choice"><i class="box"></i>Yes &nbsp; <i class="box"></i>No</span>
                                </div>
                            </div>
                        </section>

                        <section class="product-card product-board">
                            <div class="visual board-visual" aria-hidden="true"></div>
                            <h3>Flex Board</h3>
                            <div class="product-body">
                                @forelse($productGroups['flexBoard'] as $item)
                                    <div class="product-item"><strong>{{ $item['detail'] }}</strong><span>{{ $item['size'] }} · {{ $item['quantity'] }}</span></div>
                                @empty
                                    <div class="product-line"><strong>Size</strong><span></span></div>
                                @endforelse
                                <div class="product-options">
                                    <span>Flex Board</span><span class="choice"><i class="box"></i>Yes &nbsp; <i class="box"></i>No</span>
                                    <span>Light Board</span><span class="choice"><i class="box"></i>Yes &nbsp; <i class="box"></i>No</span>
                                </div>
                            </div>
                        </section>

                        <section class="product-card product-sticker">
                            <div class="visual roll-visual" aria-hidden="true"></div>
                            <h3>PVC Sticker Print</h3>
                            <div class="product-body">
                                @forelse($productGroups['pvcSticker'] as $item)
                                    <div class="product-item"><strong>{{ $item['detail'] }}</strong><span>{{ $item['size'] }} · {{ $item['quantity'] }}</span></div>
                                @empty
                                    <div class="product-line"><strong>Size</strong><span></span></div>
                                @endforelse
                            </div>
                        </section>

                        <section class="product-card product-finishing">
                            <h3>PVC Finishing</h3>
                            <div class="product-body" style="width:100%">
                                <div class="finish-row"><strong>Only Print</strong><span class="choice-row"><span class="choice"><i class="box"></i>Yes</span><span class="choice"><i class="box"></i>No</span></span></div>
                                <div class="finish-row"><strong>Lamination</strong><span class="choice-row"><span class="choice"><i class="box"></i>Yes</span><span class="choice"><i class="box"></i>No</span></span></div>
                                <div class="finish-row"><strong>Gloss or Matte</strong><span class="choice-row"><span class="choice"><i class="box"></i>Gloss</span><span class="choice"><i class="box"></i>Matte</span></span></div>
                                <div class="finish-row"><strong>PP Corrugate Board</strong><span class="choice-row"><span class="choice"><i class="box"></i>Yes</span><span class="choice"><i class="box"></i>No</span></span></div>
                                <div class="finish-row"><strong>Sun Board</strong><span class="choice-row"><span class="choice"><i class="box"></i>Yes</span><span class="choice"><i class="box"></i>No</span></span></div>
                                <div class="finish-row"><strong>Thickness</strong><span class="field-value"></span></div>
                                <div class="finish-row"><strong>ACP Board</strong><span class="choice-row"><span class="choice"><i class="box"></i>Yes</span><span class="choice"><i class="box"></i>No</span></span><strong style="flex-basis:auto">Regin:</strong><span class="field-value"></span></div>
                            </div>
                        </section>

                        <section class="product-card product-sign">
                            <h3>Sign Board with fitting</h3>
                            <div class="product-body" style="width:100%">
                                @foreach($productGroups['signBoard'] as $item)
                                    <div class="product-item"><strong>{{ $item['detail'] }}</strong><span>{{ $item['size'] }} · {{ $item['quantity'] }}</span></div>
                                @endforeach
                                <div class="product-line"><strong>Transport</strong><span></span></div>
                                <div class="product-line"><strong>Fixing</strong><span></span></div>
                            </div>
                        </section>

                        @if(count($productGroups['generic']))
                            <section class="product-card product-generic">
                                <h3>Paper / Digital &amp; Other Items</h3>
                                <div class="product-body">
                                    @foreach($productGroups['generic'] as $item)
                                        <div class="additional-item"><b>{{ $item['category_label'] }}</b><span>{{ $item['detail'] }} · {{ $item['material'] }} · {{ $item['size'] }} · {{ $item['side'] }} · {{ $item['quantity'] }}</span></div>
                                    @endforeach
                                </div>
                            </section>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </main>

    <script>
        function closeJobSheet() {
            if (window.opener) window.close();
            else window.history.back();
        }
        @if($autoPrint)
            window.addEventListener('load', () => window.setTimeout(() => window.print(), 350));
        @endif
    </script>
</body>
</html>
