"""Assert audit integrity and evidence consistency, without booting the application."""
from pathlib import Path
import hashlib,json,re,sqlite3,subprocess,collections
OUT=Path(__file__).resolve().parent
ROOT=OUT.parents[1]
E=OUT/'evidence'
def load(name): return json.loads((E/name).read_text(encoding='utf-8-sig'))
def digest(file): return hashlib.sha256(file.read_bytes()).hexdigest()
baseline=load('source-baseline.json')
source_changes=[p for p,h in baseline.items() if not (ROOT/p).exists() or digest(ROOT/p)!=h]
qa=load('qa-report-index.json')
historical_changes=[r['file'] for r in qa if digest(ROOT/r['file'])!=r['sha256']]
assert not source_changes,source_changes
assert not historical_changes,historical_changes
expected=['PRODUCT_CAPABILITY_INVENTORY.md','ROLE_NEEDS_ANALYSIS.md','CLINIC_WORKFLOW_MAP.md','MISSING_FEATURES_REPORT.md','INCOMPLETE_WORKFLOWS.md','UX_PRODUCT_GAPS.md','CROSS_FEATURE_DEPENDENCIES.md','PRODUCT_DECISIONS_REQUIRED.md','PRIORITIZED_BACKLOG.md','DEVELOPMENT_ROADMAP.md','FINAL_PRODUCT_AUDIT.md']
assert sorted(p.name for p in OUT.glob('*.md'))==sorted(expected)
broken=[]
links=0
for name in expected:
    body=(OUT/name).read_text(encoding='utf-8')
    for match in re.finditer(r'\]\(([^\n]+?)\)',body):
        target=match.group(1)
        if target.startswith(('http:','https:','#')): continue
        path=target.split('#')[0]
        if not (OUT/path).exists() and path!='evidence/audit-validation.json': broken.append({'file':name,'link':target})
        links+=1
assert not broken,broken
gaps=load('canonical-gaps.json')
assert len(gaps)==18 and len({g['id'] for g in gaps})==18
assert all(g[k] for g in gaps for k in ['title','roles','problem','workflow','present','missing','impact','workaround','solution','size','ac','evidence','frequency','basis','scope'])
assert collections.Counter(g['priority'] for g in gaps)=={'P1':9,'P2':9}
body=(OUT/'MISSING_FEATURES_REPORT.md').read_text(encoding='utf-8')
assert len(re.findall(r'^## PG-\d+',body,re.M))==18
assert len(re.findall(r'\| 12\. Acceptance criteria',body))==18
caps=load('capabilities.json')
assert len(caps)==72
assert set(c[2] for c in caps)=={'COMPLETE','PARTIAL','MISSING','NOT REQUIRED','PRODUCT DECISION REQUIRED','NOT VERIFIED'}
tasks=load('task-review.json')
active=[r for r in tasks if not r.get('superseded_by')]
assert len(tasks)==29 and len(active)==28
assert all(r['status']=='OBSERVED' for r in active)
assert all((E/r['observed']['screenshot']).is_file() for r in active)
triage=load('page-error-triage.json')
pending=next(r['pending_ui'] for r in triage if 'pending_ui' in r)
assert pending=={'intercepted_requests':1,'submitting':True,'button_disabled':False,'backend_request_blocked':True}
runtime=load('runtime.json')
assert 'clinicos-deep-product-' in runtime['root']
assert Path(runtime['database']).parent==Path(runtime['root'])
def database(path): return sqlite3.connect(Path(path).as_uri()+'?mode=ro',uri=True)
with database(runtime['database']) as db, database(Path(runtime['source_fixture'])/'audit.sqlite') as old:
    fixture=load('fixture.json');p=fixture['tenants']['dental']['patient_id']
    assert db.execute('select is_active from patients where id=?',(p,)).fetchone()[0]==1
    protected={}
    for table in ['patients','subscription_periods','addon_periods','payments','medical_records','medical_record_revisions','prescriptions','prescription_items','patient_allergies','visits','vital_signs','diagnoses','treatments']:
        current=db.execute(f'SELECT * FROM {table} ORDER BY id').fetchall()
        original=old.execute(f'SELECT * FROM {table} ORDER BY id').fetchall()
        # Patient activation was explicitly restored; profile data must also stay identical.
        assert current==original,table
        protected[table]={'rows':len(current),'identical_to_source_disposable_fixture':True}
    appointments_delta=db.execute('SELECT COUNT(*) FROM appointments').fetchone()[0]-old.execute('SELECT COUNT(*) FROM appointments').fetchone()[0]
    assert appointments_delta==1,appointments_delta
    for table in ['appointments','queues']:
        current=set(db.execute(f'SELECT * FROM {table}').fetchall())
        original=set(old.execute(f'SELECT * FROM {table}').fetchall())
        assert original<=current,table
        assert len(current)-len(original)==1,table
before=(E/'git-before.txt').read_text(encoding='utf-8-sig').splitlines()
now=subprocess.check_output(['git','status','--short'],cwd=ROOT).decode('utf-8').splitlines()
outside=lambda rows:[r for r in rows if not r[3:].replace('\\','/').startswith('qa-audit/')]
assert outside(before)==outside(now),'Git status changed outside audit directory'
result={'status':'PASS','checks':{'source_and_test_baseline_files_unchanged':len(baseline),'historical_qa_reports_unchanged':len(qa),'requested_markdown_documents':len(expected),'local_report_links_checked':links,'broken_links':len(broken),'canonical_gaps':len(gaps),'gap_acceptance_criteria_blocks':18,'capabilities':len(caps),'active_browser_observations':len(active),'historical_harness_record_preserved':1,'pg18_submit_ui_reproduced':pending,'protected_dummy_tables':protected,'dummy_appointments_added_ui22':appointments_delta,'patient_ui22_activation_restored':True,'git_status_outside_audit_unchanged':True},'functional_suite_rerun':False,'user_database_opened':False,'source_mutations':source_changes,'historical_report_mutations':historical_changes,'note':'PASS describes audit-document/evidence integrity checks, not a functional suite, product completeness percentage, human validation or production readiness.'}
(E/'audit-validation.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps({'status':'PASS','files_unchanged':len(baseline),'historical_reports_unchanged':len(qa),'documents':len(expected),'links':links,'gaps':len(gaps),'browser_observations':len(active)},ensure_ascii=False))
