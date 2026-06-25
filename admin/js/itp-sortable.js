(function(){
    document.querySelectorAll('.itp-sortable').forEach(function(table){
        var ths=table.querySelectorAll('thead th[data-sort]');
        ths.forEach(function(th){
            th.style.cursor='pointer';
            th.style.userSelect='none';
            th.insertAdjacentHTML('beforeend',' <span class="itp-sort-arrow" style="font-size:10px;color:#9ca3af;">⇅</span>');
            th.addEventListener('click',function(){
                var tbody=table.querySelector('tbody');
                // Get only data rows (not detail expansion rows)
                var rows=Array.from(tbody.querySelectorAll('tr:not(.itp-det):not(.itp-empty-row)'));
                if(rows.length<2)return;
                var asc=th.dataset.dir!=='asc';
                // Reset all arrows
                ths.forEach(function(h){
                    h.dataset.dir='';
                    var a=h.querySelector('.itp-sort-arrow');
                    if(a){a.textContent='⇅';a.style.color='#9ca3af';}
                });
                th.dataset.dir=asc?'asc':'desc';
                var arrow=th.querySelector('.itp-sort-arrow');
                if(arrow){arrow.textContent=asc?'▲':'▼';arrow.style.color='#1d2327';}
                var type=th.dataset.sort;
                var ci=Array.from(th.parentNode.children).indexOf(th);
                rows.sort(function(a,b){
                    var ac=a.cells[ci],bc=b.cells[ci];
                    if(!ac||!bc)return 0;
                    var av=ac.hasAttribute('data-v')?ac.getAttribute('data-v'):ac.textContent.trim();
                    var bv=bc.hasAttribute('data-v')?bc.getAttribute('data-v'):bc.textContent.trim();
                    if(type==='num'){
                        av=parseFloat(String(av).replace(/[^0-9.\-]/g,''))||0;
                        bv=parseFloat(String(bv).replace(/[^0-9.\-]/g,''))||0;
                        return asc?av-bv:bv-av;
                    }
                    return asc?String(av).localeCompare(String(bv)):String(bv).localeCompare(String(av));
                });
                // Re-append rows; for Live Feed, move detail rows with their parent
                rows.forEach(function(r){
                    tbody.appendChild(r);
                    var next=r.nextElementSibling;
                    // If the original next sibling was a detail row, it's now detached — find by ID
                    var rid=r.getAttribute('onclick');
                    if(rid){
                        var match=rid.match(/'(itp-sd-\d+)'/);
                        if(match){
                            var det=document.getElementById(match[1]);
                            if(det)tbody.appendChild(det);
                        }
                    }
                });
            });
        });
    });
})();
