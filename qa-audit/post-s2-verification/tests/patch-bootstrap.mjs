import{readFileSync,writeFileSync}from'node:fs';import{join}from'node:path';
const e=join(import.meta.dirname,'../evidence'),r=JSON.parse(readFileSync(join(e,'runtime.json'))),file=r.root+'/application.php';let source=readFileSync(file,'utf8');
source=source.replace(/^if \(getenv\('DB_CONNECTION'\).*$/m,`if (!str_contains(getenv('AUDIT_ROOT'), '/clinicos-post-s2-') || !((getenv('DB_CONNECTION') === 'mysql' && getenv('DB_PORT') === '13318' && str_starts_with(getenv('DB_DATABASE'), 'clinicos_post_s2_')) || (getenv('DB_CONNECTION') === 'sqlite' && str_starts_with(getenv('DB_DATABASE'), getenv('AUDIT_ROOT').'/')))) { throw new RuntimeException('Post-S2 isolation guard'); }`);
writeFileSync(file,source);console.log('Private bootstrap restricted to post-S2 test databases');
const router=r.root+'/router.php';writeFileSync(router,readFileSync(router,'utf8').replace("config('database.default') !== 'sqlite'","!in_array(config('database.default'), ['sqlite', 'mysql'], true)"));
