<style>
    .gunsas-scroll-x {
        -webkit-overflow-scrolling: touch;
        overflow-x: auto;
    }

    .gunsas-break-anywhere {
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    .gunsas-mobile-cards {
        display: none;
    }

    .gunsas-action-row {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: flex-end;
    }

    .gunsas-action-button {
        align-items: center;
        display: inline-flex;
        justify-content: center;
    }

    @media (max-width: 768px) {
        .gunsas-responsive-table {
            display: none;
        }

        .gunsas-mobile-cards {
            display: grid;
            gap: 0.75rem;
        }

        .fi-wi-chart,
        .fi-wi-chart > div {
            min-width: 0;
        }

        .fi-wi-chart .fi-section-header {
            padding: 1rem 1rem 0.5rem !important;
        }

        .fi-wi-chart .fi-section-header-heading {
            font-size: 1rem;
            line-height: 1.5rem;
        }

        .fi-wi-chart .fi-section-content {
            padding: 0.75rem !important;
        }

        .fi-wi-chart canvas {
            min-height: 220px;
            max-height: 280px;
        }
    }

    @media (max-width: 640px) {
        .fi-page {
            max-width: 100%;
            min-width: 0;
            overflow-x: clip;
        }

        .fi-main,
        .fi-page > section,
        .fi-page form,
        .fi-section,
        .fi-wi-widget,
        .fi-ta-ctn,
        .fi-fo-component-ctn {
            max-width: 100%;
            min-width: 0;
        }

        .fi-main {
            padding-inline: 0.75rem !important;
        }

        .fi-header {
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .fi-header-heading {
            font-size: 1.25rem;
            line-height: 1.75rem;
            overflow-wrap: anywhere;
        }

        .fi-ta-content,
        .fi-ta-table-ctn,
        .fi-ta-selection-cell,
        .fi-ta-cell {
            min-width: 0;
        }

        .fi-ta-content,
        .fi-ta-table-ctn {
            -webkit-overflow-scrolling: touch;
            overflow-x: auto;
        }

        .fi-ta-header-toolbar,
        .fi-ta-filters,
        .fi-ta-actions,
        .fi-ac {
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .fi-btn,
        .fi-input-wrp,
        .fi-select-input,
        .fi-text-input {
            max-width: 100%;
        }

        .fi-modal-window {
            max-width: calc(100vw - 1rem) !important;
            width: calc(100vw - 1rem) !important;
        }

        .gunsas-action-row {
            display: grid;
            justify-content: stretch;
        }

        .gunsas-action-button {
            width: 100%;
        }
    }
</style>
