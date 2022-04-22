import Order from '../PageObjects/objects'
if(Cypress.env('runDiscountsTests')){
describe('Presta 1.7', function () {

    it('TC#10: Create Discounts', function () {
        const ord = new Order()
        ord.admin()
        ord.create_discounts()
    })

    it('TC#11: CC fixed discount', function () {
        cy.fixture('config').then((admin) => {
            if (admin.CC_TERMINAL_NAME != "") {
                process(admin.CC_TERMINAL_NAME, 'cc', 'fixed')
            } else {
                cy.log('CC_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#12: Klarna fixed discount', function () {
        cy.fixture('config').then((admin) => {
            if (admin.KLARNA_DKK_TERMINAL_NAME != "") {
                process(admin.KLARNA_DKK_TERMINAL_NAME, 'klarna', 'fixed')
            } else {
                cy.log('KLARNA_DKK_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#13: CC percentage discount', function () {
        cy.fixture('config').then((admin) => {
            if (admin.CC_TERMINAL_NAME != "") {
                process(admin.CC_TERMINAL_NAME, 'cc', 'percentage')
            } else {
                cy.log('CC_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#14: Klarna percentage discount', function () {
        cy.fixture('config').then((admin) => {
            if (admin.KLARNA_DKK_TERMINAL_NAME != "") {
                process(admin.KLARNA_DKK_TERMINAL_NAME, 'klarna', 'percentage')
            } else {
                cy.log('KLARNA_DKK_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#15: CC - Fixed Specific Discounts', function () {
        cy.fixture('config').then((admin) => {
            if (admin.CC_TERMINAL_NAME != "") {
                const ord = new Order()
                ord.admin()
                ord.create_spec_discounts('fixed')
                process(admin.CC_TERMINAL_NAME, 'cc', '')
            } else {
                cy.log('CC_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#16: Klarna - Fixed Specific Discounts', function () {
        cy.fixture('config').then((admin) => {
            if (admin.KLARNA_DKK_TERMINAL_NAME != "") {
                const ord = new Order()
                ord.admin()
                ord.create_spec_discounts('fixed')
                process(admin.KLARNA_DKK_TERMINAL_NAME, 'klarna', '')
            } else {
                cy.log('KLARNA_DKK_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#17: CC - Percentage Specific Discounts', function () {
        cy.fixture('config').then((admin) => {
            if (admin.CC_TERMINAL_NAME != "") {
                const ord = new Order()
                ord.admin()
                ord.create_spec_discounts('percentage')
                process(admin.CC_TERMINAL_NAME, 'cc', '')
            } else {
                cy.log('CC_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#18: Klarna - Percentage Specific Discounts', function () {
        cy.fixture('config').then((admin) => {
            if (admin.KLARNA_DKK_TERMINAL_NAME != "") {
                const ord = new Order()
                ord.admin()
                ord.create_spec_discounts('percentage')
                process(admin.KLARNA_DKK_TERMINAL_NAME, 'klarna', '')
            } else {
                cy.log('KLARNA_DKK_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#19: Multiple - CC - Fixed Cart & Fixed Specific Discounts', function () {
        cy.fixture('config').then((admin) => {
            if (admin.CC_TERMINAL_NAME != "") {
                const ord = new Order()
                ord.admin()
                ord.create_spec_discounts('fixed')
                process(admin.CC_TERMINAL_NAME, 'cc', 'fixed')
            } else {
                cy.log('CC_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#20: Multiple - CC - Percentage Cart & Fixed Specific Discounts', function () {
        cy.fixture('config').then((admin) => {
            if (admin.CC_TERMINAL_NAME != "") {
                const ord = new Order()
                ord.admin()
                ord.create_spec_discounts('fixed')
                process(admin.CC_TERMINAL_NAME, 'cc', 'percentage')
            } else {
                cy.log('CC_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#21: Multiple - CC - Fixed Cart & Percentage Specific Discounts', function () {
        cy.fixture('config').then((admin) => {
            if (admin.CC_TERMINAL_NAME != "") {
                const ord = new Order()
                ord.admin()
                ord.create_spec_discounts('percentage')
                process(admin.CC_TERMINAL_NAME, 'cc', 'fixed')
            } else {
                cy.log('CC_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#22: iDEAL fixed discount', function () {
        cy.fixture('config').then((admin) => {
            if (admin.iDEAL_EUR_TERMINAL != "") {
                process(admin.iDEAL_EUR_TERMINAL, 'ideal', 'fixed')
            } else {
                cy.log('iDEAL_EUR_TERMINAL skipped')
            }
        })
    })
    it('TC#23: iDEAL percentage discount', function () {
        cy.fixture('config').then((admin) => {
            if (admin.iDEAL_EUR_TERMINAL != "") {
                process(admin.iDEAL_EUR_TERMINAL, 'ideal', 'percentage')
            } else {
                cy.log('iDEAL_EUR_TERMINAL skipped')
            }
        })
    })

    it('TC#24: Multiple - iDEAL - Fixed Cart & Percentage Specific Discounts', function () {
        cy.fixture('config').then((admin) => {
            if (admin.iDEAL_EUR_TERMINAL != "") {
                const ord = new Order()
                ord.admin()
                ord.create_spec_discounts('percentage')
                process(admin.iDEAL_EUR_TERMINAL, 'ideal', 'fixed')
            } else {
                cy.log('iDEAL_EUR_TERMINAL skipped')
            }
        })
    })

    it('TC#25: Multiple - iDEAL - Percentage Cart & Fixed Specific Discounts', function () {
        cy.fixture('config').then((admin) => {
            if (admin.iDEAL_EUR_TERMINAL != "") {
                const ord = new Order()
                ord.admin()
                ord.create_spec_discounts('fixed')
                process(admin.iDEAL_EUR_TERMINAL, 'ideal', 'percentage')
            } else {
                cy.log('iDEAL_EUR_TERMINAL skipped')
            }
        })
    })


    function process(terminal_name, func_name, discount = '') {
        const ord = new Order()
        ord.visit()
        if (func_name == 'ideal') {
            cy.get('body').then(($body) => {
                if ($body.text().includes('€') === false) {
                    ord.admin()
                    ord.change_currency_to_EUR_for_iDEAL()
                    ord.set_default_currency_EUR()
                    ord.re_save_EUR_currency_config()
                    ord.visit()
                }

            })
        } else {
            cy.get('body').then(($body) => {
                if ($body.text().includes('kr') === false) {
                    ord.admin()
                    ord.change_currency_to_DKK()
                    ord.set_default_currency_DKK()
                    ord.re_save_DKK_currency_config()
                    ord.visit()
                }
            })
        }

        ord.addproduct(discount)
        cy.get('body').then(($a) => {
            if ($a.find("label:contains('" + terminal_name + "')").length) {

                if (func_name == 'cc') {
                    ord.cc_payment(terminal_name)
                } else if (func_name == 'klarna') {
                    ord.klarna_payment(terminal_name)
                } else if (func_name == 'ideal') {
                    ord.ideal_payment(terminal_name)
                }
                ord.admin()
                if (func_name == 'ideal') {
                    ord.ideal_refund()
                }
                else {
                    ord.capture()
                    ord.refund()
                }
            } else {
                cy.log(terminal_name + ' not found in page')
            }
        })

    }
})
}