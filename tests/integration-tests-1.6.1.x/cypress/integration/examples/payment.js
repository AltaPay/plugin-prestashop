import Order from '../PageObjects/objects'

describe('Presta 1.6', function () {

    it('TC#1: CC full capture and refund', function () {
        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        cy.get('body').then(($body) => {
            if ($body.text().includes('€')) {
                ord.admin()
                ord.change_currency_to_DKK()
                ord.clear_cache()
                ord.re_save_DKK_currency_config()
                ord.visit()
            }
        })
        ord.addproduct()
        cy.fixture('config').then((admin) => {
            if (admin.CC_TERMINAL_NAME != "") {
                cy.get('body').then(($a) => {
                    if ($a.find("a:contains('" + admin.CC_TERMINAL_NAME + "')").length) {
                        ord.cc_payment(admin.CC_TERMINAL_NAME)
                        ord.admin()
                        ord.capture()
                        ord.refund()
                    } else {
                        cy.log(admin.CC_TERMINAL_NAME + ' not found in page')
                    }

                })

            }
            else {
                cy.log('CC_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#2: Klarna full capture and refund', function () {

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        cy.get('body').then(($body) => {
            if ($body.text().includes('€')) {
                ord.admin()
                ord.change_currency_to_DKK()
                ord.clear_cache()
                ord.re_save_DKK_currency_config()
                ord.visit()
            }
        })
        ord.addproduct()
        cy.fixture('config').then((admin) => {
            if (admin.KLARNA_DKK_TERMINAL_NAME != "") {
                cy.get('body').then(($a) => {
                    if ($a.find("a:contains('" + admin.KLARNA_DKK_TERMINAL_NAME + "')").length) {
                        ord.klarna_payment(admin.KLARNA_DKK_TERMINAL_NAME)
                        ord.admin()
                        ord.capture()
                        ord.refund()
                    } else {
                        cy.log(admin.KLARNA_DKK_TERMINAL_NAME + ' not found in page')
                    }

                })

            }
            else {
                cy.log('KLARNA_DKK_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#3: CC partial capture', function () {

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        cy.get('body').then(($body) => {
            if ($body.text().includes('€')) {
                ord.admin()
                ord.change_currency_to_DKK()
                ord.clear_cache()
                ord.re_save_DKK_currency_config()
                ord.visit()
            }
        })
        ord.addpartial_product()
        ord.addproduct()
        cy.fixture('config').then((admin) => {
            if (admin.CC_TERMINAL_NAME != "") {
                cy.get('body').then(($a) => {
                    if ($a.find("a:contains('" + admin.CC_TERMINAL_NAME + "')").length) {
                        ord.cc_payment(admin.CC_TERMINAL_NAME)
                        ord.admin()
                        ord.partial_capture()
                    } else {
                        cy.log(admin.CC_TERMINAL_NAME + ' not found in page')
                    }

                })

            }
            else {
                cy.log('CC_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#4: Klarna partial capture', function () {

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        cy.get('body').then(($body) => {
            if ($body.text().includes('€')) {
                ord.admin()
                ord.change_currency_to_DKK()
                ord.clear_cache()
                ord.re_save_DKK_currency_config()
                ord.visit()
            }
        })
        ord.addpartial_product()
        ord.addproduct()
        cy.fixture('config').then((admin) => {
            if (admin.KLARNA_DKK_TERMINAL_NAME != "") {
                cy.get('body').then(($a) => {
                    if ($a.find("a:contains('" + admin.KLARNA_DKK_TERMINAL_NAME + "')").length) {
                        ord.klarna_payment(admin.KLARNA_DKK_TERMINAL_NAME)
                        ord.admin()
                        ord.partial_capture()
                    } else {
                        cy.log(admin.KLARNA_DKK_TERMINAL_NAME + ' not found in page')
                    }

                })

            }
            else {
                cy.log('KLARNA_DKK_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#5: CC partial refund', function () {

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        cy.get('body').then(($body) => {
            if ($body.text().includes('€')) {
                ord.admin()
                ord.change_currency_to_DKK()
                ord.clear_cache()
                ord.re_save_DKK_currency_config()
                ord.visit()
            }
        })
        ord.addpartial_product()
        ord.addproduct()
        cy.fixture('config').then((admin) => {
            if (admin.CC_TERMINAL_NAME != "") {
                cy.get('body').then(($a) => {
                    if ($a.find("a:contains('" + admin.CC_TERMINAL_NAME + "')").length) {
                        ord.cc_payment(admin.CC_TERMINAL_NAME)
                        ord.admin()
                        ord.capture()
                        ord.partial_refund()
                    } else {
                        cy.log(admin.CC_TERMINAL_NAME + ' not found in page')
                    }

                })

            }
            else {
                cy.log('CC_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#6: Klarna partial refund', function () {

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        cy.get('body').then(($body) => {
            if ($body.text().includes('€')) {
                ord.admin()
                ord.change_currency_to_DKK()
                ord.clear_cache()
                ord.re_save_DKK_currency_config()
                ord.visit()
            }
        })
        ord.addpartial_product()
        ord.addproduct()
        cy.fixture('config').then((admin) => {
            if (admin.KLARNA_DKK_TERMINAL_NAME != "") {
                cy.get('body').then(($a) => {
                    if ($a.find("a:contains('" + admin.KLARNA_DKK_TERMINAL_NAME + "')").length) {
                        ord.klarna_payment(admin.KLARNA_DKK_TERMINAL_NAME)
                        ord.admin()
                        ord.capture()
                        ord.partial_refund()
                    } else {
                        cy.log(admin.KLARNA_DKK_TERMINAL_NAME + ' not found in page')
                    }

                })

            }
            else {
                cy.log('KLARNA_DKK_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#7: CC release payment', function () {

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        cy.get('body').then(($body) => {
            if ($body.text().includes('€')) {
                ord.admin()
                ord.change_currency_to_DKK()
                ord.clear_cache()
                ord.re_save_DKK_currency_config()
                ord.visit()
            }
        })
        ord.addproduct()
        cy.fixture('config').then((admin) => {
            if (admin.CC_TERMINAL_NAME != "") {
                cy.get('body').then(($a) => {
                    if ($a.find("a:contains('" + admin.CC_TERMINAL_NAME + "')").length) {
                        ord.cc_payment(admin.CC_TERMINAL_NAME)
                        ord.admin()
                        ord.release_payment()
                    } else {
                        cy.log(admin.CC_TERMINAL_NAME + ' not found in page')
                    }

                })

            }
            else {
                cy.log('CC_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#8: Klarna release payment', function () {

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        cy.get('body').then(($body) => {
            if ($body.text().includes('€')) {
                ord.admin()
                ord.change_currency_to_DKK()
                ord.clear_cache()
                ord.re_save_DKK_currency_config()
                ord.visit()
            }
        })
        ord.addproduct()
        cy.fixture('config').then((admin) => {
            if (admin.KLARNA_DKK_TERMINAL_NAME != "") {
                cy.get('body').then(($a) => {
                    if ($a.find("a:contains('" + admin.KLARNA_DKK_TERMINAL_NAME + "')").length) {
                        ord.klarna_payment(admin.KLARNA_DKK_TERMINAL_NAME)
                        ord.admin()
                        ord.release_payment()
                    } else {
                        cy.log(admin.KLARNA_DKK_TERMINAL_NAME + ' not found in page')
                    }

                })

            }
            else {
                cy.log('KLARNA_DKK_TERMINAL_NAME skipped')
            }
        })
    })

    it('TC#9: iDeal Full Capture & Refund', function () {

        const ord = new Order()
        ord.clrcookies()
        ord.visit()
        cy.get('body').then(($body) => {
            if ($body.text().includes('€')) {
                ord.addproduct()
                cy.fixture('config').then((admin) => {
                    if (admin.iDEAL_EUR_TERMINAL != "") {
                        cy.get('body').then(($a) => {
                            if ($a.find("a:contains('" + admin.iDEAL_EUR_TERMINAL + "')").length) {
                                ord.ideal_payment(admin.iDEAL_EUR_TERMINAL)
                                ord.admin()
                                ord.ideal_refund()
                            } else {
                                cy.log(admin.iDEAL_EUR_TERMINAL + ' not found in page')
                                this.skip()
                            }
                        })
                    }
                    else {
                        cy.log('iDEAL_EUR_TERMINAL skipped')
                        this.skip()
                    }
                })
            }
            else {
                ord.admin()
                ord.change_currency_to_EUR_for_iDEAL()
                ord.clear_cache()
                ord.re_save_EUR_currency_config()
                ord.visit()
                ord.addproduct()
                cy.fixture('config').then((admin) => {
                    if (admin.iDEAL_EUR_TERMINAL != "") {
                        cy.get('body').then(($a) => {
                            if ($a.find("a:contains('" + admin.iDEAL_EUR_TERMINAL + "')").length) {
                                ord.ideal_payment(admin.iDEAL_EUR_TERMINAL)
                                ord.admin()
                                ord.ideal_refund()
                            } else {
                                cy.log(admin.iDEAL_EUR_TERMINAL + ' not found in page')
                                this.skip()
                            }
                        })
                    }
                    else {
                        cy.log('iDEAL_EUR_TERMINAL skipped')
                        this.skip()
                    }
                })
            }
        })
    })
})
