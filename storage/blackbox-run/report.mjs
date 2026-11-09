import fs from 'node:fs/promises';
import {FileBlob, SpreadsheetFile} from '@oai/artifact-tool';
import {results,actualRules} from './results.mjs';
const inputs=JSON.parse(await fs.readFile('storage/blackbox-inputs.json','utf8'));
const metadata=JSON.parse(await fs.readFile('storage/blackbox-run/metadata.json','utf8'));
const outputDir='outputs/blackbox-20261018';
await fs.mkdir(outputDir,{recursive:true});
const totals={Pass:0,Fail:0,Blocked:0,'Not Run':0};
for(const item of inputs){
 const role=item.sheets.at(-1).name;
 const wb=await SpreadsheetFile.importXlsx(await FileBlob.load(item.path));
 const sheet=wb.worksheets.getItem(role);
 const rows=results[role];
 if(rows.length!==item.sheets.at(-1).rows.length-1)throw new Error('Row count mismatch '+role);
 sheet.getRange(`K2:M${rows.length+1}`).values=rows;
 for(let i=0;i<rows.length;i++){
   const row=i+2;
   const oldHeight=metadata[role][role].heights[String(row)]||15;
   // Only grow rows where new result text needs more space; preserve template column widths.
   const lines=Math.max(Math.ceil(rows[i][0].length/30),Math.ceil(rows[i][2].length/24));
   const needed=lines*14+12;
   if(needed>oldHeight)sheet.getRange(`A${row}:M${row}`).format.rowHeight=needed;
 }
 const summary=wb.worksheets.getItem('Summary');
 summary.getRange('B5').values=[['FarSell — account creation']];
 summary.getRange('B6').values=[['9950500 / Laravel 13.26.1']];
 summary.getRange('B7').values=[['PHP 8.4; SQLite; HTTP/browser']];
 summary.getRange('B8').values=[['Codex']];
 summary.getRange('B9').values=[[new Date('2026-10-18T00:00:00Z')]];
 summary.getRange('B9').setNumberFormat('yyyy-mm-dd');
 for(const [i,r] of ['Buyer','Seller','Logistics','Rider'].entries()){
   const row=i+13;
   if(r!==role) summary.getRange(`B${row}:F${row}`).formulas=[['=0','=0','=0','=0','=0']];
 }
 const assumptions=wb.worksheets.getItem('Assumptions');
 assumptions.getRange('D5:D20').values=actualRules.map(r=>[r]);
 assumptions.getRange('D5:D20').format.wrapText=true;
 // Existing rule rows already have large wrapped text areas; grow only changed rows if needed.
 for(let i=0;i<actualRules.length;i++){
   const row=i+5;
   const oldHeight=metadata[role].Assumptions.heights[String(row)]||15;
   const needed=Math.ceil(actualRules[i].length/65)*14+12;
   if(needed>oldHeight)assumptions.getRange(`A${row}:D${row}`).format.rowHeight=needed;
 }
 wb.recalculate();
 console.log(role,(await wb.inspect({kind:'table',range:`${role}!K2:M4`,include:'values,formulas',tableMaxRows:3,tableMaxCols:3,maxChars:1600})).ndjson);
 const errors=await wb.inspect({kind:'match',searchTerm:'#REF!|#DIV/0!|#VALUE!|#NAME\\?|#NUM!|#NULL!',options:{useRegex:true,maxResults:20},summary:'Final formula scan',maxChars:1200});
 console.log(role,'ERRORS',errors.ndjson);
 for(const [label,sheetName,range] of [['top',role,'I1:M4'],['end',role,`K${rows.length-1}:M${rows.length+1}`],['summary','Summary','A4:G17'],['rules','Assumptions','C5:D9']]){
   const preview=await wb.render({sheetName,range,scale:1,format:'png'});
   await fs.writeFile(`storage/blackbox-run/${role}-${label}.png`,new Uint8Array(await preview.arrayBuffer()));
 }
 const xlsx=await SpreadsheetFile.exportXlsx(wb);
 const filename=`${outputDir}/Blackbox_Test_Results_Account_Creation-${role}.xlsx`;
 await xlsx.save(filename);
 const counts={Pass:0,Fail:0,Blocked:0,'Not Run':0};
 for(const row of rows){counts[row[1]]++;totals[row[1]]++;}
 console.log(role,filename,JSON.stringify(counts));
}
console.log('TOTAL',JSON.stringify(totals));
