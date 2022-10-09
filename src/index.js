import Connections from "./Connections";
import Plugins from "./Plugins";

const { render, Fragment } = wp.element;

const SmolblogAdmin = () => (
  <Fragment>
    <h2>Connections</h2>
    <Connections />
    <h2>Plugins</h2>
    <Plugins />
  </Fragment>
);

render(<SmolblogAdmin />, document.getElementById("smolblog-admin-app"));
