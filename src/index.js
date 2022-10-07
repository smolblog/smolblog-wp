import Connections from "./Connections";

const { render, Fragment } = wp.element;

const SmolblogAdmin = () => (
  <Fragment>
    <h2>Connections</h2>
    <Connections />
  </Fragment>
);

render(<SmolblogAdmin />, document.getElementById("smolblog-admin-app"));
