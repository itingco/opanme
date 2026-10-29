(() => {
'use strict';
const cfg=window.itemLookupConfig||{};
const code=document.getElementById('item_code'),name=document.getElementById('item_name'),qty=document.getElementById('qty');
const results=document.getElementById('item-results'),status=document.getElementById('lookup-status'),options=document.getElementById('barcode-options');
const preview=document.getElementById('qr-preview'),submit=document.getElementById('submit-button'),form=document.getElementById('label-form');
if(!code||!cfg.endpoint)return;
let debounce,searchAbort,unitAbort,selected='',items=[],index=-1,unitReady=false,barcodeRows=[];
const close=()=>{results.hidden=true;results.replaceChildren();items=[];index=-1};
const selectedRadio=()=>options.querySelector('input[name="barcode_id"]:checked');
const update=()=>{
 const found=selectedRadio(),isFallback=unitReady&&!barcodeRows.length;
 preview.textContent=found?found.dataset.barcode:(isFallback&&selected&&qty.value?`${selected}-${qty.value}`:'-');
 submit.disabled=!(selected&&unitReady&&(isFallback||found));
};
const resetUnits=msg=>{unitAbort?.abort();unitReady=false;barcodeRows=[];options.replaceChildren();const x=document.createElement('div');x.className='label-message';x.textContent=msg;options.append(x);update()};
async function loadUnits(itemCode){
 resetUnits('Memeriksa master barcode dan satuan…');
 const controller=new AbortController();unitAbort=controller;
 try{
  const url=new URL(cfg.endpoint,location.origin);url.searchParams.set('item_code',itemCode);
  const response=await fetch(url,{headers:{Accept:'application/json'},signal:controller.signal});if(!response.ok)throw new Error('Gagal mengambil master barcode.');
  const json=await response.json();if(selected!==itemCode)return;
  barcodeRows=Array.isArray(json.barcodes)?json.barcodes.filter(row=>row.id&&row.barcode&&row.uom):[];
  options.replaceChildren();
  if(!barcodeRows.length){const d=document.createElement('div');d.className='label-message';d.textContent='Tidak ditemukan barcode master. QR fallback akan memakai kode barang - qty.';options.append(d)}
  else barcodeRows.forEach((row,i)=>{
   const label=document.createElement('label');label.className='label-unit-option';
   const radio=document.createElement('input');radio.type='radio';radio.name='barcode_id';radio.value=String(row.id);radio.dataset.barcode=row.barcode;radio.required=true;
   radio.checked=String(row.id)===String(cfg.previousBarcodeId||'')||(!cfg.previousBarcodeId&&i===0);
   const details=document.createElement('span');const title=document.createElement('strong');title.textContent=`${row.uom}`;
   const value=document.createElement('small');value.textContent=`Barcode: ${row.barcode}`;details.append(title,value);label.append(radio,details);options.append(label);
   radio.addEventListener('change',update);
  });
  unitReady=true;update();
 }catch(e){if(e.name==='AbortError')return;resetUnits('Master barcode gagal dibaca. Coba pilih ulang barang.');status.textContent='Koneksi barcode gagal'}
}
function selectItem(item){selected=item.code;code.value=item.code;name.value=item.name;status.textContent='Barang dipilih';close();loadUnits(item.code);qty.focus()}
function render(rows){items=rows;index=-1;results.replaceChildren();
 if(!rows.length){const e=document.createElement('div');e.className='lookup-empty';e.textContent='Barang tidak ditemukan.';results.append(e)}
 rows.forEach((item,i)=>{const b=document.createElement('button');b.type='button';b.className='lookup-option';const s=document.createElement('strong');s.textContent=item.code;const n=document.createElement('span');n.textContent=item.name;b.append(s,n);b.addEventListener('click',()=>selectItem(item));results.append(b)});results.hidden=false;
}
async function lookup(term,exactOnly=false){searchAbort?.abort();const controller=new AbortController();searchAbort=controller;status.textContent='Mencari…';
 try{const url=new URL(cfg.endpoint,location.origin);url.searchParams.set('q',term);const r=await fetch(url,{headers:{Accept:'application/json'},signal:controller.signal});if(!r.ok)throw new Error();const json=await r.json();const rows=Array.isArray(json.data)?json.data:[];
 const exact=rows.find(x=>String(x.code).toLowerCase()===term.toLowerCase());if(exactOnly){if(exact)selectItem(exact);else status.textContent='Pilih barang dari hasil pencarian';return}
 status.textContent=`${rows.length} hasil`;render(rows);
 }catch(e){if(e.name!=='AbortError'){status.textContent='Koneksi pencarian gagal';close()}}
}
code.addEventListener('input',()=>{selected='';name.value='';resetUnits('Pilih barang terlebih dahulu.');clearTimeout(debounce);searchAbort?.abort();const v=code.value.trim();if(!v){status.textContent='';close();return}debounce=setTimeout(()=>lookup(v),260)});
code.addEventListener('keydown',e=>{const opts=results.querySelectorAll('.lookup-option');if(results.hidden||!opts.length)return;if(e.key==='ArrowDown'||e.key==='ArrowUp'){e.preventDefault();index=(index+(e.key==='ArrowDown'?1:-1)+opts.length)%opts.length;opts[index].focus()}else if(e.key==='Escape')close()});
code.addEventListener('blur',()=>setTimeout(close,180));qty.addEventListener('input',update);
document.addEventListener('click',e=>{if(!results.contains(e.target)&&e.target!==code)close()});
form?.addEventListener('submit',e=>{update();if(submit.disabled){e.preventDefault();status.textContent='Pilih barang dan satuan barcode terlebih dahulu.'}});
update();if(cfg.previousCode)lookup(cfg.previousCode,true);
})();
