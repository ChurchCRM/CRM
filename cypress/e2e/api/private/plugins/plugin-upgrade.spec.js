describe('Community plugin upgrade (fake registry)', () => {
    const ID = 'upgrade-fixture';
    const PATH = `/plugins/api/plugins/${ID}`;

    const approve = (version) =>
        cy.task('pluginFixtures:write', { id: ID, version }).then(([entry]) =>
            cy.makePrivateAdminAPICall('POST', '/plugins/api/registry/refresh').then(() => entry.downloadUrl)
        );

    const installedPlugin = () =>
        cy.makePrivateAdminAPICall('GET', '/plugins/api/plugins').then((r) => r.body.data.find((p) => p.id === ID));

    before(() => {
        cy.makePrivateAdminAPICall('DELETE', PATH, null, [200, 400]);
    });

    after(() => {
        cy.makePrivateAdminAPICall('DELETE', PATH, null, [200, 400]);
        cy.task('pluginFixtures:clear');
        cy.makePrivateAdminAPICall('POST', '/plugins/api/registry/refresh');
    });

    it('installs the approved 1.0.0 release and enables it', () => {
        approve('1.0.0').then((downloadUrl) => {
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/install', { downloadUrl }).then((r) => {
                expect(r.body.data.upgradedFrom).to.eq(null);
            });
        });
        cy.makePrivateAdminAPICall('POST', `${PATH}/enable`);
        installedPlugin().then((p) => {
            expect(p.version).to.eq('1.0.0');
            expect(p.isActive).to.be.true;
        });
    });

    it('refuses to reinstall the same version', () => {
        approve('1.0.0').then((downloadUrl) => {
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/install', { downloadUrl }, 409);
        });
    });

    it('upgrades in place to a newer approved release and stays enabled', () => {
        approve('1.1.0').then((downloadUrl) => {
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/install', { downloadUrl }).then((r) => {
                expect(r.body.data.upgradedFrom).to.eq('1.0.0');
                expect(r.body.message).to.contain('1.0.0').and.contain('1.1.0');
            });
        });
        installedPlugin().then((p) => {
            expect(p.version).to.eq('1.1.0');
            expect(p.isActive, 'enabled state survives the upgrade').to.be.true;
        });
    });

    it('refuses to downgrade to an older approved release', () => {
        approve('0.9.0').then((downloadUrl) => {
            cy.makePrivateAdminAPICall('POST', '/plugins/api/plugins/install', { downloadUrl }, 409);
        });
        installedPlugin().then((p) => expect(p.version).to.eq('1.1.0'));
    });

    it('leaves no upgrade backup directory behind', () => {
        cy.task('pluginFixtures:leftovers', ID).should('deep.equal', []);
    });
});
