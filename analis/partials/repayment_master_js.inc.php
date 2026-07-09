<?php
/**
 * JS helper repayment — dasar & persentase dari master parameter (disetujui).
 * Wajib: $RPC_PERSEN_MAKS, $RPC_DASAR, $RPC_DASAR_LABEL
 */
if (!isset($RPC_PERSEN_MAKS) || !isset($RPC_DASAR)) {
    return;
}
?>
        const RPC_PERSEN_MAKS = <?= json_encode((float) $RPC_PERSEN_MAKS, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const RPC_DASAR = <?= json_encode($RPC_DASAR, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        const RPC_DASAR_LABEL = <?= json_encode($RPC_DASAR_LABEL ?? getRepaymentDasarLabel($RPC_DASAR), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        function resolveRepaymentBasis(ctx) {
            switch (RPC_DASAR) {
                case 'gaji_bersih':
                    return ctx.gajiBersih || 0;
                case 'gaji_bersih_pendapatan':
                    return (ctx.gajiBersih || 0) + (ctx.pendapatan || 0);
                case 'laba_bersih':
                    return ctx.labaBersih || 0;
                case 'net_cashflow':
                default:
                    return ctx.netCashflow || 0;
            }
        }

        function hitungRepayment(nilaiDasar) {
            return nilaiDasar * (RPC_PERSEN_MAKS / 100);
        }

        function hitungRepaymentFromContext(ctx) {
            return hitungRepayment(resolveRepaymentBasis(ctx));
        }

        function formatRpcMasterLabel() {
            return RPC_PERSEN_MAKS + '% × ' + RPC_DASAR_LABEL;
        }
