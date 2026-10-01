<p align="center">
    <a href="https://tos.fiksutiliratkaisut.fi/" target="_blank"><img width="200" src="https://tos.fiksutiliratkaisut.fi/img/tawasul-logo.png"></a><br>
    TawasulOS is a flexible, open source school management platform designed <br>
    to make life better for teachers, students, parents and schools.
</p>

------

TawasulOS Core
===========
The Core repository represents the bulk of TawasulOS, including all of its primary functionality. The core can be extended through the use of modules and themes, which are provided separately. See the [Extend](https://tos.fiksutiliratkaisut.fi/extend/) page for more info.

TawasulOS is open source, and maintained for the benefit of teachers, students, parents and schools.

## Documentation

For full documentation, visit [docs.tos.fiksutiliratkaisut.fi](https://docs.tos.fiksutiliratkaisut.fi).

## Installation & Support

For installation instructions, visit [Getting Started: Installing TawasulOS](https://docs.tos.fiksutiliratkaisut.fi/introduction/installing-tawasul)

For support visit [ask.tos.fiksutiliratkaisut.fi](https://ask.tos.fiksutiliratkaisut.fi) or see [our documentation](https://docs.tos.fiksutiliratkaisut.fi).

## Docker Development Setup

This Docker setup allows developers to run a local development setup so contributors can run TawasulOS without manually installing and configuring PHP, Apache, and MySQL on their machine.

## Prerequisites

- Install [Docker Desktop](https://www.docker.com/products/docker-desktop)

## Start The Development Environment

From the project root, run:

```bash
./up.sh
```

This script will:
- Check `.env` file exists and create it if not.
- Check that Docker Desktop is installed and running
- Build and start the TawasulOS development server container

TawasulOS will be available at **http://localhost:8080**

## Useful Commands

Stop the development environment and remove volumes:
```bash
./up.sh down
```

View live container logs:
```bash
./up.sh logs
```

## Cutting Edge
If you want to run the latest version of TawasulOS, prerelease, you can get the source from our [GitHub repository](https://github.com/TawasulOSEdu/core). Remember, though, it is not stable, and you may lose data. This is not for the faint of heart.

For installation instructions, be sure to follow the instructions for [Cutting Edge Code](https://docs.tos.fiksutiliratkaisut.fi/introduction/installation-options/cutting-edge-code).

## Translation

Thanks to our amazing volunteers, TawasulOS is available in many different languages. We use the online tool [POEditor](https://poeditor.com), which enables our volunteer translators to collaborate and track their translation progress. Huge thanks to POEditor for their support of open source projects and making this tool available for our community. If you would like to help translate TawasulOS, please email support@tos.fiksutiliratkaisut.fi and [learn more here](https://tos.fiksutiliratkaisut.fi/about/#languages). Your help would be most appreciated!

## Contributing

We welcome community contribution and aim to ensure TawasulOS is an open and friendly environment. Information about contributing, submitting issues, and pull requests can be found in the following docs:

- [**Contributor Guide**](https://github.com/TawasulOSEdu/core/blob/master/.github/CONTRIBUTING.md) - Learn more about how you can contribute to TawasulOS, from code to non-code contributions alike.

- [**Code of Conduct**](https://github.com/TawasulOSEdu/core/blob/master/.github/CODE_OF_CONDUCT.md) - Our pledge to foster a welcoming community and a positive environment for anyone to participate in.

- [**Developer Workflow**](https://docs.tos.fiksutiliratkaisut.fi/development/getting-started/developer-workflow) - If you want to get involved in the development process, check out our workflow and [GitHub repository](https://github.com/TawasulOSEdu/core). Generally there will be a development branch with the latest code, as per our [Development Road Map](https://docs.tos.fiksutiliratkaisut.fi/development/tawasul-road-map).

## License

TawasulOS is licensed under GNU General Public License v3.0. You can obtain a copy of the license [here](https://github.com/TawasulOSEdu/core/blob/master/LICENSE).
