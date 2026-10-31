"""Generate editable, uncompressed draw.io diagrams from reviewed application flows and schema metadata."""
from pathlib import Path
import html
import json
import re
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'docs' / 'diagrams'
SCHEMA = json.loads((OUT / 'schema-metadata.json').read_text(encoding='utf-8'))['tables']
BASE = 'whiteSpace=wrap;html=0;fontFamily=Helvetica;fontSize=14;strokeColor=#64748b;fillColor=#ffffff;'


class Diagram:
    def __init__(self, filename):
        self.file = OUT / filename
        self.xml = ET.Element('mxfile', host='app.diagrams.net', agent='RHU project diagram generator', version='24.7.17')

    def page(self, name, width=1120, height=1600):
        diagram = ET.SubElement(self.xml, 'diagram', id='page-' + str(len(self.xml) + 1), name=name)
        model = ET.SubElement(diagram, 'mxGraphModel', dx='1120', dy='900', grid='1', gridSize='10', guides='1',
                              tooltips='1', connect='1', arrows='1', fold='1', page='1', pageScale='1',
                              pageWidth=str(width), pageHeight=str(height), math='0', shadow='0')
        self.root = ET.SubElement(model, 'root')
        ET.SubElement(self.root, 'mxCell', id='0')
        ET.SubElement(self.root, 'mxCell', id='1', parent='0')
        self.number = 0
        return self

    def node(self, key, label, x, y, w=340, h=72, kind='process', parent='1'):
        shape = {'process': 'rounded=0;', 'end': 'rounded=1;arcSize=50;fillColor=#e8f5ef;',
                 'decision': 'rhombus;fillColor=#fff8e5;', 'note': 'shape=note;fillColor=#f8fafc;align=left;spacing=12;',
                 'title': 'text;strokeColor=none;fillColor=none;align=left;fontStyle=1;fontSize=23;',
                 'text': 'text;strokeColor=none;fillColor=none;align=left;',
                 'actor': 'shape=umlActor;verticalLabelPosition=bottom;verticalAlign=top;align=center;',
                 'usecase': 'ellipse;fillColor=#f0f8f4;',
                 'boundary': 'rounded=0;fillColor=none;verticalAlign=top;align=center;spacingTop=12;fontStyle=1;',
                 'table': 'rounded=0;html=1;align=left;verticalAlign=top;spacing=0;fontFamily=Courier New;fontSize=12;'}[kind]
        cell = ET.SubElement(self.root, 'mxCell', id=key, value=label, style=BASE + shape, vertex='1', parent=parent)
        ET.SubElement(cell, 'mxGeometry', x=str(x), y=str(y), width=str(w), height=str(h), **{'as': 'geometry'})
        return key

    def edge(self, source, target, label='', style='', points=None):
        self.number += 1
        cell = ET.SubElement(self.root, 'mxCell', id='edge-' + str(self.number), value=label,
                             style='edgeStyle=orthogonalEdgeStyle;rounded=0;html=0;fontSize=12;strokeColor=#64748b;labelBackgroundColor=#ffffff;endArrow=block;endFill=1;' + style,
                             edge='1', parent='1', source=source, target=target)
        geometry = ET.SubElement(cell, 'mxGeometry', relative='1', **{'as': 'geometry'})
        if points:
            arr = ET.SubElement(geometry, 'Array', **{'as': 'points'})
            for x, y in points:
                ET.SubElement(arr, 'mxPoint', x=str(x), y=str(y))

    def title(self, title, subtitle):
        self.node('title', title, 40, 20, 1020, 44, 'title')
        self.node('subtitle', subtitle, 40, 67, 1020, 54, 'text')

    def save(self):
        ET.indent(self.xml)
        ET.ElementTree(self.xml).write(self.file, encoding='utf-8', xml_declaration=True)


def flow(filename, title, source, steps, links, notes=''):
    d = Diagram(filename).page(title, height=max(1600, 160 + len(steps) * 120))
    d.title(title, 'As implemented • 31 October 2026\nSource: ' + source)
    for key, label, x, y, kind in steps:
        d.node(key, label, x, y, h=100 if kind == 'decision' else 76, kind=kind)
    for link in links:
        d.edge(*link)
    if notes:
        d.node('notes', notes, 680, 1250, 360, 200, 'note')
    d.save()


def make_flows():
    x, r = 230, 690
    flow('receiving-flowchart.drawio', 'RHU stock receiving', 'StockLogController::store', [
        ('s', 'Start: RHU admin or staff\nrecords received stock', x, 145, 'end'),
        ('v', 'Validate medicine, positive quantity,\nreceipt date and batch details', x, 260, 'process'),
        ('ok', 'Input valid and\nRHU assigned?', x, 375, 'decision'),
        ('bad', 'Show validation / access error\nCorrect input before resubmitting', r, 387, 'end'),
        ('tx', 'Begin database transaction', x, 515, 'process'),
        ('batch', 'Find or create matching inventory batch\nMedicine + RHU + batch + expiry', x, 630, 'process'),
        ('log', 'Create stock intake log\nQuantity, date, user and receipt details', x, 745, 'process'),
        ('add', 'Increase batch available stock\nby received quantity', x, 860, 'process'),
        ('success', 'Transaction succeeds?', x, 975, 'decision'),
        ('rollback', 'Roll back database changes\nReturn error', r, 987, 'end'),
        ('commit', 'Commit; record activity audit\nShow updated stock logs', x, 1115, 'process'),
        ('e', 'End', x, 1230, 'end'),
    ], [('s','v'),('v','ok'),('ok','bad','No'),('ok','tx','Yes'),('tx','batch'),('batch','log'),('log','add'),
        ('add','success'),('success','rollback','No'),('success','commit','Yes'),('commit','e')],
        'This page describes RHU stock intake. PHO deliveries use InventoryController::storePhoReceive: create a shipment and its items, update PHO batches, and add receipt logs in one transaction. Receipt logs are not usage demand.')

    flow('dispensing-flowchart.drawio', 'Medicine dispensing', 'DispensingController::store; StockMovementService', [
        ('s','Start: authorized RHU user\nselects medicine and enters dispensing',x,145,'end'),
        ('validate','Validate patient name, quantity, date,\nmedicine and prescription requirements',x,260,'process'),
        ('ok','Input and access\nchecks pass?',x,375,'decision'),
        ('bad','Return validation / access error',r,387,'end'),
        ('lock','Begin transaction; lock available\nunexpired batches in FEFO order',x,515,'process'),
        ('enough','Combined available stock\ncovers requested quantity?',x,630,'decision'),
        ('no','Roll back; report insufficient stock\nRemove newly uploaded file on failure',r,642,'end'),
        ('deduct','Take stock from earliest-expiry batch\nContinue across batches until fulfilled',x,770,'process'),
        ('record','Save dispensing record and each\nbatch movement and release log',x,885,'process'),
        ('commit','Commit transaction; record audit\nDisplay success and updated balances',x,1000,'process'),
        ('e','End',x,1115,'end'),
    ], [('s','validate'),('validate','ok'),('ok','bad','No'),('ok','lock','Yes'),('lock','enough'),
        ('enough','no','No'),('enough','deduct','Yes'),('deduct','record'),('record','commit'),('commit','e')],
        'FEFO = First Expiry, First Out. Midwives operate within their account/facility and patient permissions. Edit/delete paths reverse exact historical batch movements before replacing or removing the dispensing transaction. No patient_id FK is invented: dispensing stores patient_name.')

    flow('transfer-flowchart.drawio', 'Inter-facility stock transfer', 'TransferController; TransferService', [
        ('s','Start: authorized admin / staff\ncreates a transfer request',x,145,'end'),
        ('pending','Request status: pending\nNo stock reserved yet',x,260,'process'),
        ('accept','Source accepts request?\nCancel / reject otherwise',x,375,'decision'),
        ('cancel1','Cancel or reject pending request\nNo stock reversal required',r,387,'end'),
        ('stock','Lock source batch: one unexpired\nbatch has enough available stock?',x,515,'decision'),
        ('fail','Keep request pending\nReturn insufficient-stock error',r,527,'end'),
        ('reserve','Reserve: available -= quantity\nallocated += quantity; status accepted',x,655,'process'),
        ('receipt','Receipt confirmed or\nautomatically processed?',x,770,'decision'),
        ('cancel2','If cancelled: reverse reservation\nRestore availability; status cancelled',r,782,'end'),
        ('check','Lock request and source; verify\nreservation and batch are still valid',x,910,'process'),
        ('apply','Release source reservation\nCredit matching destination batch\nWrite both movements and transfer logs',x,1025,'process'),
        ('received','Commit status received\nNotify both facilities; audit completion',x,1140,'process'),
        ('e','End: repeat receipt does not\ncredit stock a second time',x,1255,'end'),
    ], [('s','pending'),('pending','accept'),('accept','cancel1','No / cancelled'),('accept','stock','Yes'),
        ('stock','fail','No'),('stock','reserve','Yes'),('reserve','receipt'),('receipt','cancel2','Cancelled'),
        ('receipt','check','Yes'),('check','apply','Valid'),('apply','received'),('received','e')],
        'Accepted transfers can remain awaiting receipt. Invalid/missing/expired source batches cause receipt to fail without crediting destination stock. Older accepted transfers reserve under lock before receipt. Automatic processing uses the same completion service; a scheduler trigger is not an unconditional immediate receipt.')

    flow('forecasting-flowchart.drawio', 'Medicine stock forecasting', 'StockTrendService; StockTrendModel; ForecastService', [
        ('s','Start: view forecast for one RHU\nor provincial medicine / facility entries',x,145,'end'),
        ('logs','Load valid, non-voided dispensing\nand local allocation release logs\nExclude receipts, transfers and corrections',x,260,'process'),
        ('weeks','Group up to 12 completed rolling\nseven-day periods; exclude today\nZero gaps only after first release',x,375,'process'),
        ('history','At least one observed\ncompleted period?',x,490,'decision'),
        ('none','Label no history; no fitted trend\nUse zero projection; do not claim\nmeasured stock coverage',r,502,'end'),
        ('minimum','At least 4 completed\nperiods available?',x,630,'decision'),
        ('average','Fit weekly-average fallback\nLabel limited history',r,642,'process'),
        ('ols','Fit OLS: y = intercept + slope × t\nCompute direction and historical R²',x,770,'process'),
        ('predict','Project weekly demand; clamp below 0\nConvert to daily demand; prorate periods',x,910,'process'),
        ('metrics','Sum 90-day need; calculate shortage,\ncoverage and lead-time + safety demand\nUse available, unexpired inventory',x,1025,'process'),
        ('e','Display trend, stock status,\ncoverage and reorder point',x,1140,'end'),
    ], [('s','logs'),('logs','weeks'),('weeks','history'),('history','none','No'),('history','minimum','Yes'),
        ('minimum','average','No'),('minimum','ols','Yes'),('average','predict'),('ols','predict'),('predict','metrics'),('metrics','e')],
        'Thresholds and horizons are configurable. Medicine weeks are rolling seven-day bins ending yesterday, not ISO reporting weeks. This demand forecast is driven by stock releases, not disease case totals. R² measures historical fit, not forecast accuracy.')

    flow('disease-analysis-flowchart.drawio', 'Disease reporting and case forecasting', 'DiseaseReportController; DiseasePeriodService; DiseaseAnalysisService', [
        ('s','Start: RHU user selects disease\nand weekly or monthly reporting',x,145,'end'),
        ('period','Save dated total; determine ISO week\nor calendar-month boundaries\nPrevent duplicate disease / RHU / period',x,260,'process'),
        ('complete','Period ended and user\nconfirms complete total?',x,375,'decision'),
        ('partial','Keep provisional / needs review\nDo not use as a case observation',r,387,'end'),
        ('grid','Build completed-period grid\nKeep confirmed zero; missing, partial\nor conflicting totals stay null',x,515,'process'),
        ('models','Evaluate two models independently',x,630,'process'),
        ('ols','OLS: ≥4 confirmed observations\nPreserve time positions across gaps\nProject only if latest period confirmed',x,745,'process'),
        ('holt','Holt: ≥4 consecutive confirmed\nperiods ending at latest completed period\nOtherwise mark unavailable',x,860,'process'),
        ('validate','With ≥8 consecutive periods:\nselect weights on training prefix;\nvalidate on last 3 one-step observations',x,975,'process'),
        ('e','Display 4 full-period projections,\ncompleteness, R² and available MAE\nFirst forecast is the current period',x,1115,'end'),
    ], [('s','period'),('period','complete'),('complete','partial','No'),('complete','grid','Yes'),('grid','models'),
        ('models','ols'),('ols','holt'),('holt','validate'),('validate','e')],
        'The model boxes show calculation order, not dependence: Holt does not use OLS output. Forecasts are clipped at zero. Latest missing reporting suppresses projection. Manual outbreak flags are separate from these models; no automatic outbreak declaration or disease-to-medicine quantity forecast is implemented.')


def make_usecases():
    d = Diagram('system-use-cases.drawio')
    roles = [
        ('Public visitor', 'No authentication required for public medicine search.', [
            'Search public medicine catalog', 'View medicine details', 'Select an RHU and check availability', 'Check public stock availability', 'Read privacy / terms / accessibility']),
        ('PHO administrator', 'Admin account without an RHU assignment; provincial scope.', [
            'View provincial dashboard and forecasts', 'Receive DOH shipments into PHO stock', 'Distribute / transfer stock to RHUs', 'Manage medicine reference records',
            'Manage RHUs and user accounts', 'View stock reports and activity logs', 'Review redistribution suggestions']),
        ('RHU administrator', 'Admin account with an RHU assignment; facility scope.', [
            'Monitor RHU stock and forecasts', 'Receive stock / maintain inventory', 'Dispense medicines and manage releases', 'Manage allocations and supply requests',
            'Request / process facility transfers', 'Manage RHU patients and clinical records', 'Record disease reports and view case forecasts', 'Manage permitted accounts / reference records']),
        ('RHU staff', 'Staff account with assigned RHU; administrative-only operations remain restricted.', [
            'View RHU dashboard and forecasts', 'Record stock intake and view inventory', 'Dispense medicines', 'Process permitted supply / transfer actions',
            'Manage RHU patient services and calendar', 'Record disease reports and view case forecasts', 'Export permitted RHU reports']),
        ('Midwife', 'Assigned RHU and patient scope; dispensing history restricted by account ownership.', [
            'View midwife dashboard', 'Access assigned patient records', 'Record immunization and maternal care', 'Dispense within permitted patient scope',
            'Submit / track own supply requests', 'Confirm own allocation receipt', 'Record disease reports and case analysis', 'View calendar and notifications']),
    ]
    for role, note, actions in roles:
        d.page(role, 1180, 1160)
        d.title('Use cases — ' + role, note + '\nRepresentative functional use cases; consult routes and controller guards for operation-level permissions.')
        d.node('system', 'RHU Inventory and Forecasting System', 300, 145, 810, 920, 'boundary')
        d.node('actor', role, 85, 470, 85, 135, 'actor')
        for i, action in enumerate(actions):
            key = 'use-' + str(i)
            d.node(key, action, 480, 205 + i * 100, 500, 78, 'usecase')
            d.edge('actor', key, style='endArrow=none;startArrow=none;edgeStyle=none;')
    d.save()


def make_overall_usecases():
    d = Diagram('overall-use-case-diagram.drawio').page('Overall use case diagram', 1800, 2040)
    d.title('RHU Inventory and Forecasting System — Overall Use Cases',
            'Actors and their main interactions. Association lines show participation, not processing order.\nDetailed role pages remain available in system-use-cases.drawio.')
    d.node('system', 'RHU Inventory and Forecasting System', 420, 140, 940, 1710, 'boundary')
    actors = [
        ('pho', 'PHO administrator', 110, 460),
        ('admin', 'RHU administrator', 110, 950),
        ('staff', 'RHU staff', 110, 1460),
        ('public', 'Public visitor', 1570, 280),
        ('midwife', 'Midwife', 1570, 1230),
    ]
    for key, label, x, y in actors:
        d.node(key, label, x, y, 90, 130, 'actor')
    cases = [
        ('search', 'Search medicines and public stock availability', ['public']),
        ('map', 'View medicine details and RHU availability', ['public']),
        ('account', 'Sign in / sign out and manage own account', ['pho','admin','staff','midwife']),
        ('manage', 'Manage permitted user accounts', ['pho','admin']),
        ('shipments', 'Receive DOH shipments into PHO stock', ['pho']),
        ('intake', 'Record RHU stock intake and view inventory', ['admin','staff']),
        ('transfer', 'Distribute supplies / process facility transfers', ['pho','admin','staff']),
        ('dispense', 'Dispense medicines within permitted scope', ['admin','staff','midwife']),
        ('clinical', 'Manage permitted patient and clinical services', ['admin','staff','midwife']),
        ('disease', 'Record disease reports and view case analysis', ['admin','staff','midwife']),
        ('supply', 'Submit own supply requests / confirm own allocation receipt', ['admin','staff','midwife']),
        ('forecast', 'View stock reports and medicine forecasts', ['pho','admin','staff']),
        ('notifications', 'View notifications', ['pho','admin','staff','midwife']),
    ]
    for index, (key, label, participants) in enumerate(cases):
        d.node(key, label, 630, 210 + index * 122, 520, 84, 'usecase')
        for actor in participants:
            d.edge(actor, key, style='edgeStyle=none;endArrow=none;startArrow=none;')
    d.node('scope', 'SCOPE NOTES\nPHO = provincial administrator; RHU = assigned facility. Each action remains subject to controller checks.\nMidwife clinical access is limited to assigned patients; allocations require recipient ownership. RHU staff do not inherit administrator-only permissions.\nPublic access requires no staff account. Disease case analysis and medicine stock forecasting are separate functions.', 80, 1890, 1640, 110, 'note')
    d.save()


def make_overall_flow():
    d = Diagram('overall-system-flowchart.drawio').page('Overall system flow', 2100, 2520)
    d.title('RHU Inventory and Forecasting System — Overall Flow',
            'Public access → authenticated role → permitted activity → validation → processing → results → continue / logout.\nActivity boxes list alternatives, not mandatory sequential tasks. Based on the implemented routes, guards and services.')
    nodes = [
        ('start', 'Start: open the public domain\nor management subdomain', 850, 145, 340, 72, 'end'),
        ('entry', 'Use public services\nor sign in?', 850, 260, 340, 100, 'decision'),
        ('public', 'Public domain: medicine search\nNo staff login required', 200, 272, 340, 76, 'process'),
        ('browse', 'Search medicines and stock availability\nView medicine information for a selected RHU', 200, 400, 340, 80, 'process'),
        ('public-login', 'Sign in as a\nstaff member?', 200, 525, 340, 100, 'decision'),
        ('public-end', 'End public visit', 200, 675, 340, 72, 'end'),
        ('login', 'Management subdomain only\nEnter staff username and password', 850, 400, 340, 76, 'process'),
        ('valid-login', 'Credentials valid and\naccount active?', 850, 515, 340, 100, 'decision'),
        ('login-error', 'Show login error / rate limit\nRetry when permitted', 1450, 527, 340, 76, 'process'),
        ('session', 'Create renewed session\nLoad current role and facility', 850, 655, 340, 76, 'process'),
        ('role', 'Route to the appropriate\nrole dashboard', 850, 770, 340, 100, 'decision'),
        ('pho', 'PHO administrator dashboard\nAdmin without RHU assignment', 180, 940, 340, 76, 'process'),
        ('rhu', 'RHU administrator / staff dashboard\nAccount assigned to a facility', 850, 940, 340, 76, 'process'),
        ('midwife', 'Midwife dashboard\nAssigned facility and patient scope', 1520, 940, 340, 76, 'process'),
        ('pho-actions', 'Select a permitted activity:\n• Receive DOH shipments\n• Distribute / transfer supplies\n• Manage medicines, RHUs and accounts\n• Monitor provincial reports and forecasts', 180, 1060, 340, 180, 'process'),
        ('rhu-actions', 'Select a permitted activity:\n• Receive / manage stock\n• Dispense and process allocations / transfers\n• Manage patient services and calendar\n• Record disease reports\n• View RHU reports and forecasts', 850, 1060, 340, 180, 'process'),
        ('midwife-actions', 'Select a permitted activity:\n• Access assigned patients\n• Record immunization / maternal care\n• Dispense within permitted scope\n• Request supplies / confirm own receipt\n• Record disease reports / case analysis', 1520, 1060, 340, 180, 'process'),
        ('guard', 'For the selected action: check role,\nfacility, patient / record ownership\nand validate submitted input', 850, 1320, 340, 86, 'process'),
        ('allowed', 'Access and input\nchecks pass?', 850, 1450, 340, 100, 'decision'),
        ('rejected', 'Show errors / deny access\nKeep database unchanged\nCorrect input or choose permitted action', 1450, 1462, 340, 86, 'process'),
        ('action', 'Read information\nor change records?', 850, 1590, 340, 100, 'decision'),
        ('write', 'Execute selected workflow\nFor stock changes: lock batches, check\nexpiry / availability, apply exact deltas', 850, 1730, 340, 86, 'process'),
        ('read', 'Load authorized view data\nIf selected: calculate medicine forecast\nor disease trend / case forecast\nNo stock change for viewing a forecast', 1450, 1715, 340, 110, 'process'),
        ('save', 'Save related records and commit\nStock workflows: preserve logs / movements\nFailure → roll back and show error', 850, 1855, 340, 86, 'process'),
        ('result', 'Present result / updated records\nShow reports, charts and notifications\nwhere applicable', 850, 1985, 340, 86, 'process'),
        ('again', 'Perform another activity?', 850, 2110, 340, 100, 'decision'),
        ('logout', 'Log out\nInvalidate staff session', 850, 2250, 340, 76, 'process'),
        ('end', 'End', 850, 2365, 340, 72, 'end'),
        ('notes', 'READING THIS OVERVIEW\n\nOnly actions allowed for the signed-in role are available. RHU staff do not gain administrator-only permissions.\n\nMedicine forecasts use recorded stock releases. Disease case forecasts use confirmed, completed reports.\n\nScheduled alerts and automatic transfer processing run separately when the deployment scheduler is configured.', 160, 1480, 430, 360, 'note'),
    ]
    for key, label, x, y, w, h, kind in nodes:
        d.node(key, label, x, y, w, h, kind)
    links = [
        ('start','entry'), ('entry','public','Public'), ('public','browse'), ('browse','public-login'),
        ('public-login','public-end','No'), ('public-login','login','Yes', '', [(625,575),(625,440)]),
        ('entry','login','Sign in'), ('login','valid-login'), ('valid-login','login-error','No'),
        ('login-error','login','Retry', '', [(1900,565),(1900,375),(1020,375)]),
        ('valid-login','session','Yes'), ('session','role'),
        ('role','pho','PHO admin'), ('role','rhu','RHU admin / staff'), ('role','midwife','Midwife'),
        ('pho','pho-actions'), ('rhu','rhu-actions'), ('midwife','midwife-actions'),
        ('pho-actions','guard','Selected action'), ('rhu-actions','guard','Selected action'), ('midwife-actions','guard','Selected action'),
        ('guard','allowed'), ('allowed','rejected','No'),
        ('rejected','role','Choose again', '', [(2020,1505),(2020,820)]),
        ('allowed','action','Yes'), ('action','read','Read / analyze'), ('action','write','Change records'),
        ('write','save'), ('save','result'), ('read','result','View result', '', [(1620,1940),(1260,1940),(1260,2028)]),
        ('result','again'), ('again','role','Yes — return to dashboard', '', [(70,2160),(70,820)]),
        ('again','logout','No'), ('logout','end'),
    ]
    for link in links:
        d.edge(*link)
    d.save()


def logical_relationships():
    """Extract belongsTo relationships only when both tables and columns exist in the snapshot."""
    result = set()
    model_tables = {}
    models = {}
    for path in (ROOT / 'app/Models').glob('*.php'):
        source = path.read_text(encoding='utf-8')
        explicit = re.search(r"protected\s+\$table\s*=\s*'([^']+)'", source)
        snake = re.sub(r'(?<!^)(?=[A-Z])', '_', path.stem).lower()
        default = snake[:-1] + 'ies' if snake.endswith('y') else snake + 's'
        model_tables[path.stem] = explicit.group(1) if explicit else default
        models[path.stem] = source
    for model, source in models.items():
        child = model_tables[model]
        if child not in SCHEMA:
            continue
        columns = {c['name'] for c in SCHEMA[child]['columns']}
        for match in re.finditer(r"belongsTo\((\w+)::class(?:,\s*'([^']+)')?", source):
            parent = model_tables.get(match.group(1))
            field = match.group(2) or re.sub(r'(?<!^)(?=[A-Z])', '_', match.group(1)).lower() + '_id'
            if field in columns and parent in SCHEMA:
                result.add((child, field, parent, 'id'))
    # Direct service/query references that are not exposed as belongsTo methods.
    for child, field, parent in [
        ('stock_logs','inventory_id','inventory'), ('stock_movements','inventory_id','inventory'),
        ('stock_movements','reverses_id','stock_movements'), ('disease_reports','outbreak_closed_by','users'),
        ('doh_shipment_items','medicine_id','medicines'), ('doh_shipments','received_by','users'),
        ('public_users','household_id','households'), ('households','health_unit_id','health_units'),
    ]:
        if child in SCHEMA and field in {c['name'] for c in SCHEMA[child]['columns']}:
            result.add((child, field, parent, 'id'))
    return result


def make_erd():
    d = Diagram('database-erd.drawio')
    physical = {(t, f['column_name'], f['parent_table'], f['parent_column']) for t, meta in SCHEMA.items() for f in meta['foreign_keys']}
    logical = logical_relationships() - physical
    groups = [
        ('Stock master and history', ['health_units','medicines','users','inventory','stock_logs','stock_movements']),
        ('Distribution and dispensing', ['health_units','medicines','users','inventory','allocations','transfer_requests','dispensing_records','supply_requests']),
        ('Patients and households', ['health_units','users','households','public_users','prenatal_visits','postpartum_visits','family_planning']),
        ('Immunization services', ['health_units','public_users','users','inventory','immunization_records','aefi_reports','vaccination_schedules','immunizations']),
        ('Disease reports and mapping', ['health_units','users','diseases','medicines','disease_reports','disease_medicine_map']),
        ('Programs scheduling and operations', ['health_units','users','doh_programs','calendar_events','rhu_immunization_schedules','monthly_reports','notifications','activity_logs','cold_chain_logs']),
        ('Shipments and reference tables', ['users','medicines','doh_shipments','doh_shipment_items','immunization_schedule','epi_schedule','midwife_barangay','migrations']),
    ]
    covered = set()
    for name, names in groups:
        names = [n for n in names if n in SCHEMA]
        covered.update(names)
        heights = {n: 54 + 21 * len(SCHEMA[n]['columns']) for n in names}
        rows = [names[i:i+3] for i in range(0, len(names), 3)]
        ys = []
        y = 175
        for row in rows:
            ys.append(y)
            y += max(heights[n] for n in row) + 135
        d.page(name, 1580, y + 200)
        d.title('Database ERD — ' + name, 'Live schema snapshot: 31 October 2026 • all columns shown\nSolid = enforced FK; dashed = application relationship. PK/UK are schema keys; ? = nullable. Parent 1 / 0..1 → child 0..many (unless unique).')
        positions = {}
        for i, table in enumerate(names):
            columns = SCHEMA[table]['columns']
            refs = {f[1] for f in physical | logical if f[0] == table}
            lines = []
            for c in columns:
                tags = ('PK ' if c['key_type'] == 'PRI' else 'UK ' if c['key_type'] == 'UNI' else '') + ('FK ' if c['name'] in refs else '')
                typ = c['type']
                if len(typ) > 25:
                    typ = typ.split('(')[0] + '(…)'
                lines.append(tags + c['name'] + (' ?' if c['nullable'] == 'YES' else '') + ' : ' + typ)
            x, y0 = 50 + i % 3 * 510, ys[i // 3]
            positions[table] = (x, y0)
            label = '<div style="height:32px;box-sizing:border-box;padding:7px 12px;background:#e8f5ef;font-weight:bold;font-family:Helvetica;font-size:14px;">' + html.escape(table) + '</div>'
            label += '<div style="padding:10px 12px;font-family:monospace;font-size:12px;line-height:21px;">'
            label += ''.join('<div style="height:21px;white-space:nowrap;">' + html.escape(line) + '</div>' for line in lines) + '</div>'
            d.node(table, label, x, y0, 420, heights[table], 'table')
        for child, field, parent, parent_field in sorted(physical | logical):
            if child not in names or parent not in names:
                continue
            cols = SCHEMA[child]['columns']
            col = next(c for c in cols if c['name'] == field)
            index = next(i for i, c in enumerate(cols) if c['name'] == field)
            entry = min(.96, (54 + index * 21 + 8) / heights[child])
            p_index = next((i for i,c in enumerate(SCHEMA[parent]['columns']) if c['name'] == parent_field), 0)
            exit_y = min(.96, (54 + p_index * 21 + 8) / heights[parent])
            right = positions[parent][0] <= positions[child][0]
            d.edge(parent, child, field,
                   'startArrow=' + ('ERzeroToOne' if col['nullable'] == 'YES' else 'ERone') + ';endArrow=' + ('ERzeroToOne' if col['key_type'] in ['PRI','UNI'] else 'ERzeroToMany') +
                   ';startFill=0;endFill=0;exitX=' + ('1' if right else '0') + ';exitY=' + str(exit_y) + ';entryX=' + ('0' if right else '1') + ';entryY=' + str(entry) +
                   (';dashed=1;' if (child,field,parent,parent_field) in logical else ';'))
        d.node('notes', 'Cross-module tables repeat for context; connections to absent tables are on other pages.\nstock_movements.owner_type + owner_id is polymorphic, not a database FK.\ndispensing_records stores patient_name rather than a public_users patient_id.\nSome live legacy tables have fewer columns than models expect; no missing columns or relationships are fabricated.', 50, y, 1440, 130, 'note')
    if covered != set(SCHEMA):
        raise RuntimeError('Unmapped schema tables: ' + repr(set(SCHEMA) - covered))
    d.save()


def validate():
    for path in OUT.glob('*.drawio'):
        root = ET.parse(path).getroot()
        for page in root.findall('diagram'):
            model = page.find('mxGraphModel')
            cells = page.findall('./mxGraphModel/root/mxCell')
            ids = [c.get('id') for c in cells]
            assert len(ids) == len(set(ids)), (path, page.get('name'), 'duplicate ids')
            for cell in cells:
                if cell.get('edge') == '1':
                    assert cell.get('source') in ids and cell.get('target') in ids
                if cell.get('vertex') == '1':
                    geometry = cell.find('mxGeometry')
                    assert geometry is not None
                    x, y, w, h = [float(geometry.get(k, '0')) for k in ['x','y','width','height']]
                    assert min(x,y) >= 0 and min(w,h) > 0
                    assert x+w <= float(model.get('pageWidth')) and y+h <= float(model.get('pageHeight')), (path, cell.get('id'), 'outside page')
        print(f'VALID {path.name}: {len(root.findall("diagram"))} page(s)')


if __name__ == '__main__':
    make_overall_usecases()
    make_overall_flow()
    make_flows()
    make_usecases()
    make_erd()
    validate()
