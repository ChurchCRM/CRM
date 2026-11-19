import { Output, randomPassword, Services } from "~templates-utils";
import { Input } from "./meta";

export function generate(input: Input): Output {
  const password = randomPassword();
  const services: Services = [
    {
      type: "app",
      data: {
        serviceName: input.appServiceName,
        source: { type: "image", image: "churchcrm/crm:latest-php8-apache" },
        env: "CHURCHCRM_PERSISTENT_DATA=1",
        domains: [{ host: "$(EASYPANEL_DOMAIN)", port: 8080 }],
        mounts: [{ type: "volume", name: "data", mountPath: "/var/lib/churchcrm" }],
      },
    },
    {
      type: "app",
      data: {
        serviceName: input.databaseServiceName,
        source: { type: "image", image: "mariadb:11.4.5" },
        env: [
          "MARIADB_DATABASE=churchcrm",
          "MARIADB_USER=churchcrm",
          `MARIADB_PASSWORD=${password}`,
          `MARIADB_ROOT_PASSWORD=${randomPassword()}`,
        ].join("\n"),
        mounts: [{ type: "volume", name: "mysql", mountPath: "/var/lib/mysql" }],
      },
    },
  ];
  return { services };
}
