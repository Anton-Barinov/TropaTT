(function (root) {
    'use strict';

    async function run(plan, execute, reconcile, progress) {
        var result = { updated: [], failed: [], uncertain: [], remaining: [] };
        for (var index = 0; index < plan.length; index++) {
            var item = plan[index];
            progress(index, item, 'running');
            try {
                await execute(item);
                result.updated.push(item.code);
                progress(index, item, 'updated');
            } catch (error) {
                if (error && error.envelope && error.envelope.code === 'MODULE_UPDATE_FAILED') {
                    result.failed.push(item.code);
                    progress(index, item, 'failed');
                    continue;
                }
                var confirmed = false;
                try { confirmed = await reconcile(item); } catch (readError) { confirmed = false; }
                if (confirmed) {
                    result.updated.push(item.code);
                    progress(index, item, 'updated');
                    continue;
                }
                result.uncertain.push(item.code);
                result.remaining = plan.slice(index + 1).map(function (entry) { return entry.code; });
                progress(index, item, 'uncertain');
                break;
            }
        }
        return result;
    }

    if (typeof module === 'object' && module.exports) module.exports = { run: run };
    else root.CRMModuleUpdateQueue = { run: run };
})(typeof window !== 'undefined' ? window : this);
