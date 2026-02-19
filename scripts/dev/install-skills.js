const { execSync } = require("child_process");
const skills = require("../../skills.json");

skills.forEach((skill) => {
  console.log(`Installing skill: ${skill.name}`);
  execSync(
    `npx skills add ${skill.source} --skill ${skill.name} --version ${skill.version}`,
    { stdio: "inherit" },
  );
});
